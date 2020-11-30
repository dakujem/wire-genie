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
        ...$staticArgs,
    ): iterable {
        return self::core(
            $this->detector ?? self::defaultDetector(),
            $this->resolver ?? self::defaultResolver(),
        )(
            $genie,
            $target,
            ...$staticArgs,
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
     *                           function(mixed $param, Container, callable $staticArgument): mixed
     * @return callable strategy for Genie class
     */
    public static function core(callable $detector, callable $resolver): callable
    {
        return function (
            Genie $genie,
            callable|string $target,
            ...$staticArgs,
        ) use (
            $detector,
            $resolver,
        ): iterable {
            $args = array_reverse($staticArgs, true); // preserve keys!

            // prepare a callable to serve static arguments
            $next = function (string $name) use (&$args): mixed {
                // if there is an attr with given name, consume it
                if (array_key_exists($name, $args)) {
                    try {
                        return $args[$name];
                    } finally {
                        unset($args[$name]);
                    }
                }

                // if there is none, use the first available, but only if its index is numeric !
                $args = [];
                end($args);
                $key = key($args);
                if (is_int($key)) {
                    try {
                        return $args[$key];
                    } finally {
                        unset($args[$key]);
                    }
                }

                // otherwise throw to indicate there is no other argument available
                throw new ArgumentNotAvailable($name);
            };

            /** @var ParamRef $param */
            foreach ($detector($target) as $param) {
                if (!$param instanceof ParamRef) {
                    throw new InvalidConfiguration();
                }
                // skip variadic parameter(s)
                if (!$param->isVariadic()) {
                    // TODO is there any benefit in using a generator here? probably not.
                    yield $resolver($param, $genie, $next); // TODO yield with key?? $param->getName() =>
                }
            }
            // only yield the rest args with numeric indices
            yield from array_reverse(
                array_filter($args, fn(int|string $key): bool => is_int($key), ARRAY_FILTER_USE_KEY)
            );
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

    public static function defaultResolver(): callable
    {
        //
        // Fetching services from the container is always prioritized above live construction of the services.
        //
        return function (ParamRef $param, Genie $genie, callable $staticArgProvider): mixed {
            //
            // Check the "skip" hint, completely skip any automatic wiring if found.
            //
            $skip = $param->getAttributes(AttrSkip::class, AttrRef::IS_INSTANCEOF);
            if ($skip !== []) {
                /** @var Container $container */
                $container = $genie->exposeContainer(fn($c): Container => $c);

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

                $getTypes = function (ParamRef $param): array {
                    $t = $param->getType();
                    return $t instanceof ReflectionUnionType ? $t->getTypes() : [$t];
                };
                $getClassNames = function (iterable $refs): iterable {
                    /** @var ReflectionNamedType $ref */
                    foreach ($refs as $ref) {
                        if (!$ref->isBuiltin()) {
                            yield $ref->getName();
                        }
                    }
                };

                //
                // In a loop for all possible type-hinted classes,
                // try to fetch the service.
                //
                $serviceNames = $getClassNames($getTypes($param));
                foreach ($serviceNames as $name) {
                    if ($container->has($name)) {
                        return $container->get($name);
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
            }

            // if the type hint is a built-in type or there are no more type-hinted classes,
            // try to use a static argument from the pool
            try {
                return $staticArgProvider();
            } catch (ArgumentNotAvailable) {
                // continue...
            }

            // fall back to the default value for the parameter, if any
            if ($param->isOptional()) {
                return $param->getDefaultValue();
            }

            // return null for nullable parameters
            if ($param->allowsNull()) {
                return null;
            }

            // throw otherwise
            throw new UnresolvableArgument($param->getName());
        };
    }
}
