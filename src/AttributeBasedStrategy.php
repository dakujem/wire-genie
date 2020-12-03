<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Dakujem\Wire\Attributes\AttemptWireHint as AttrHot;
use Dakujem\Wire\Attributes\ConstructionWireHint as AttrMake;
use Dakujem\Wire\Attributes\IdentifierWireHint as AttrWire;
use Dakujem\Wire\Attributes\SuppressionWireHint as AttrSkip;
use Dakujem\Wire\Exceptions\ArgumentNotAvailable;
use Dakujem\Wire\Exceptions\InvalidConfiguration;
use Dakujem\Wire\Exceptions\UnresolvableArgument;
use Dakujem\Wire\Exceptions\UnresolvableCallArguments;
use Psr\Container\ContainerInterface as Container;
use ReflectionAttribute as AttrRef;
use ReflectionNamedType;
use ReflectionParameter as ParamRef;
use ReflectionUnionType;

/**
 * The default resolver strategy based on reflection and native attributes.
 *
 * Callable.
 * fn(Genie, callable|string, ...):iterable
 *
 * This strategy uses reflection and attributes to detect service types or service identifiers
 * that are then fetched from the service container.
 * The resulting set of arguments is then returned.
 *
 * Specifying a custom resolver and detector can greatly improve the capabilities of the strategy.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class AttributeBasedStrategy
{
    use PredictableAccess;

    /** @var callable|null */
    private $resolver;

    /** @var callable|null */
    private $detector;

    public function __construct(?callable $resolver = null, ?callable $detector = null)
    {
        $this->resolver = $resolver;
        $this->detector = $detector;
    }

    public function __invoke(
        Genie $genie,
        callable|string $target,
        ...$pool,
    ): iterable {
        return self::core(
            $this->detector ?? self::defaultDetector(),
            $this->resolver ?? self::defaultResolver(),
        )(
            $genie,
            $target,
            ...$pool,
        );
    }

    /**
     * Returns a strategy for Genie instances. The strategy is a callable that returns an iterable upon invocation.
     * fn(Genie, callable|string $target, ...$staticArgs): iterable
     *
     * The core works like this:
     * - a detector produces a list of parameters (parameter reflections by default), given a function or a class name
     * - the core iterates over the list of parameters and passes each item to the resolver
     *   along with the container instance and the next-static-argument callable
     * - the resolver is expected to return a value to be used as argument for each given parameter
     *
     * @param callable $detector the callable used to detect parameters of a callable or class constructor;
     *                           each of the parameters is passed to the $resolver;
     *                           function(callable|string):iterable
     * @param callable $resolver the callable that resolves each parameter into a respective argument;
     *                           function(mixed $param, Container, callable $staticArgument, Genie): mixed
     * @return callable strategy for Genie class
     */
    public static function core(callable $detector, callable $resolver): callable
    {
        return function (
            Genie $genie,
            callable|string $target,
            ...$pool,
        ) use (
            $detector,
            $resolver,
        ): iterable {
            $reversedPool = array_reverse($pool, true); // preserve keys!

            // prepare a callable to serve static arguments
            $next = function (?string $name) use (&$reversedPool): mixed {
                // if there is an attr with given name, consume it
                if ($name !== null && array_key_exists($name, $reversedPool)) {
                    try {
                        return $reversedPool[$name];
                    } finally {
                        unset($reversedPool[$name]);
                    }
                }

                // if there is none, use the first available, but only if its index is numeric !
                if ($name === null) {
                    end($reversedPool);
                    $key = key($reversedPool);
                    if (is_int($key)) {
                        try {
                            return $reversedPool[$key];
                        } finally {
                            unset($reversedPool[$key]);
                        }
                    }
                }

                // otherwise throw to indicate there is no other argument available
                throw ArgumentNotAvailable::arg($name);
            };

            $container = $genie->exposeContainer(fn($c): Container => $c);

            /** @var ParamRef $param */
            foreach ($detector($target) as $param) {
                if (!$param instanceof ParamRef) {
                    throw new InvalidConfiguration();
                }
                // skip variadic parameter(s)
                if (!$param->isVariadic()) {
                    // TODO is there any benefit in using a generator here? probably not.
                    yield $resolver($param, $container, $next, $genie); // TODO yield with key?? $param->getName() =>
                }
            }
            // only yield the rest args with numeric indices
            yield from array_reverse(
                array_filter($args, fn(int|string $key): bool => is_int($key), ARRAY_FILTER_USE_KEY)
            );
            // Internal warning: the resulting generator must only be unpacked, indices would be overwritten otherwise
        };
    }

    public static function defaultDetector(): callable
    {
        return function (callable|string $target): iterable {
            // Note: the reflection may be null for classes without a constructor.
            $ref = Inspector::reflectionOf($target);
            return $ref !== null ? $ref->getParameters() : [];
        };
    }

    /**
     * Priorities:
     * 1. named argument passed
     * 2. #[Wire(id)]
     * 3. type hint
     * 4. #[Hot] + type hint
     * 5. #[Make(class)]
     * 5. next unnamed argument
     * 7. default parameter value
     * 8. null
     *
     * Points 2-5 are skippable using the #[Skip] attribute.
     *
     * @return callable
     */
    public static function defaultResolver(): callable
    {
        return function (ParamRef $param, Container $container, callable $staticArgProvider, Genie $genie): mixed {
            //
            // If there is a named argument passed to the invocation, use it.
            //
            try {
                return $staticArgProvider($param->getName());
            } catch (ArgumentNotAvailable) {
                // continue...
            }

            //
            // Check the "skip" hint, completely skip any automatic wiring if found.
            //
            $skip = $param->getAttributes(AttrSkip::class, AttrRef::IS_INSTANCEOF);
            if ($skip === []) {
                //
                // Check if there is a wire hint, try to fetch the service.
                //
                // #[Wire(Foo::class)]
                //
                $wire = $param->getAttributes(AttrWire::class, AttrRef::IS_INSTANCEOF);
                foreach ($wire as $attrRef) {
                    /** @var AttrWire $attr */
                    $attr = $attrRef->newInstance();
                    $name = $attr->getIdentifier();
                    if ($container->has($name)) {
                        return $container->get($name);
                    }
                }

                //
                // In a loop for all possible type-hinted classes,
                // try to fetch the service.
                //
                $t = $param->getType();
                $refs = $t instanceof ReflectionUnionType ? $t->getTypes() : [$t];
                $serviceNames = array_filter(array_map(function (?ReflectionNamedType $ref) {
                    return $ref !== null && !$ref->isBuiltin() ? $ref->getName() : null;
                }, $refs));
                foreach ($serviceNames as $name) {
                    if ($container->has($name)) {
                        return $container->get($name);
                    }
                }

                //
                // Then, attempt to construct a service by type-hinted class names, if hinted so.
                //
                // #[Hot]
                //
                $hot = $param->getAttributes(AttrHot::class, AttrRef::IS_INSTANCEOF);
                foreach ($hot !== [] ? $serviceNames : [] as $target) {
                    try {
                        return $genie->construct($target);
                    } catch (UnresolvableCallArguments) {
                        // continue...
                    }
                }

                //
                // Attempt to construct a service if hinted so.
                //
                // #[Make(Foo::class, 42, 'arg')]
                //
                $make = $param->getAttributes(AttrMake::class, AttrRef::IS_INSTANCEOF);
                foreach ($make as $attrRef) {
                    /** @var AttrMake $attr */
                    $attr = $attrRef->newInstance();
                    try {
                        return $genie->construct($attr->getClassName(), ...$attr->getConstructorArguments());
                    } catch (UnresolvableCallArguments) {
                        // continue...
                    }
                }
            }

            //
            // If the type hint is a built-in type or there are no more type-hinted classes,
            // try to use an unnamed argument from the static pool.
            //
            try {
                return $staticArgProvider(null);
            } catch (ArgumentNotAvailable) {
                // continue...
            }

            //
            // Fall back to the default value for the parameter, if defined.
            //
            if ($param->isOptional()) {
                return $param->getDefaultValue();
            }

            //
            // Return `null` for nullable parameters.
            //
            if ($param->allowsNull()) {
                return null;
            }

            //
            // Throw otherwise.
            //
            throw UnresolvableArgument::arg($param->getName());
        };
    }
}
