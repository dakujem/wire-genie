<?php

declare(strict_types=1);

namespace Dakujem;

use Psr\Container\ContainerInterface as Container;
use ReflectionParameter;

/**
 * WireInvoker - an invoker and constructor class used for automatic callable invocation and class construction.
 *
 * The class revolves around two methods:
 * @see WireInvoker::invoke()
 * @see WireInvoker::construct()
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class WireInvoker implements Invoker, Constructor
{
    use PredictableAccess;

    /**
     * A callable that allows to customize the way service identifiers are detected.
     * @var callable function(ReflectionFunctionAbstract $reflection): ReflectionParameter[]
     */
    private $detector;

    /**
     * A callable that allows to customize the way service identifiers are detected and fetched from a container.
     * @var callable function(ReflectionParameter, Container, callable): mixed
     */
    private $resolver;

    /**
     * A callable that allows to customize the way service identifiers are detected.
     * @var callable function(iterable, Container): iterable
     */
    private $mapper;

    /**
     * Construct an instance of WireInvoker. Really? Yup!
     *
     * TODO doc-comment
     *
     * Detector, reflector and service proxy work as a pipeline to provide a service for a target's parameter:
     *      $service = $serviceProxy( $detector( $reflector( $target ) ) )
     *
     * In theory, the whole pipeline can be altered not to work with reflections,
     * there are no restriction to return types of the three callables, except for the detector.
     *
     * @param Container $container service container
     * @param callable|null $detector a callable used for identifier detection;
     *                                takes the result of $reflector, MUST return an array of service identifiers;
     *                                function(ReflectionFunctionAbstract $reflection): string[]
     * @param callable|null $serviceProxy a callable that takes a service identifier and a container instance
     *                                    and SHOULD return the requested service;
     *                                    function(string $identifier, Container $container): service
     * @param callable|null $reflector a callable used to get the reflection of the target being invoked or constructed;
     *                                 SHOULD return a reflection of the function or constructor that will be invoked;
     *                                 function($target): FunctionReflectionAbstract
     */
    public function __construct(
        private Container $container,
        ?callable $resolver = null,
        ?callable $detector = null,
        ?callable $mapper = null
    ) {
        $this->detector = $detector;
        $this->resolver = $resolver; // TODO it may be a part of the mapper directly if we use a friction reducer factory
        $this->mapper = $mapper;
    }

    /**
     * Create an instance of WireInvoker
     * by passing either a WireGenie or a container implementation instance.
     *
     * The rest of the parameters are the same as the constructor's.
     * @see WireInvoker::__construct()
     *
     * @param WireGenie|Container $source
     * @param callable|null $resolver
     * @param callable|null $detector
     * @param callable|null $mapper
     * @return self
     */
    public static function employ(
        WireGenie|Container $source,
        ?callable $resolver = null,
        ?callable $detector = null,
        ?callable $mapper = null
    ): self {
        $worker = function (Container $container) use ($resolver, $detector, $mapper) {
            return new self($container, $resolver, $detector, $mapper);
        };
        return $source instanceof WireGenie ? $source->exposeContainer($worker) : $worker($source);
    }

    /**
     * Invokes the callable $target with automatically resolved arguments.
     * Unresolved arguments are filled in from the static argument pool.
     * Returns the result of the call.
     *
     * @param callable $target callable to be invoked
     * @param mixed ...$staticArgs static argument pool
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target, ...$staticArgs): mixed
    {
        return $target(...$this->resolveArguments($target, ...$staticArgs));
    }

    /**
     * Constructs the requested object with automatically resolved constructor arguments.
     * Unresolved arguments are filled in from the static argument pool.
     * Returns the constructed instance.
     *
     * @param string $target target class name
     * @param mixed ...$staticArgs static argument pool
     * @return mixed the constructed class instance
     */
    public function construct(string $target, ...$staticArgs): mixed
    {
        return new $target(...$this->resolveArguments($target, ...$staticArgs));
    }

    /**
     * This provider instances are also callable.
     *
     * @param callable|string $target callable to be invoked or a name of a class to be constructed.
     * @param mixed ...$staticArgs static argument pool
     * @return mixed result of the $target callable invocation or an instance of the requested class
     */
    public function __invoke(callable|string $target, ...$staticArgs): mixed
    {
        return is_string($target) && class_exists($target) ?
            $this->construct($target, ...$staticArgs) :
            $this->invoke($target, ...$staticArgs);
    }

    /**
     * Works like this:
     * - a detector produces a list of parameters to be resolved, given a function or a class name
     * - the list is passed to a mapper that will map it to a list of arguments
     * - the default mapper will iterate over the list of parameters and pass each item to the resolver
     *   along with the container instance and the next static argument
     * - the resolver is expected to return a value to be used as argument for each given parameter
     *
     * @param callable|string $target a callable to be invoked or a name of a class to be constructed
     * @param mixed ...$staticArguments static arguments to fill in for parameters where identifier can not be detected
     * @return iterable
     */
    private function resolveArguments(callable|string $target, ...$staticArguments): iterable
    {
        return ($this->mapper ?? self::defaultMapper($this->resolver ?? self::defaultResolver()))(
            ($this->detector ?? self::defaultDetector())($target),
            $this->container,
            $staticArguments,
        );
    }

    private function defaultDetector(): callable
    {
        return fn(callable|string $target): iterable => (ArgInspector::reflectionOf($target))->getParameters();
    }

    private function defaultMapper(callable $resolver): callable
    {
        return function (iterable $params, Container $container, array $staticArgs) use ($resolver): iterable {
            $args = array_reverse($staticArgs, true); // preserve keys!

            // consume a static arg
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

            /** @var ReflectionParameter $param */
            foreach ($params as $param) {
                // skip variadic parameter(s)
                if (!$param->isVariadic()) {
                    // TODO is there any benefit in using a generator here? probably not.
                    yield $resolver($param, $container, $next); // TODO yield with key?? $param->getName() =>
                }
            }
            // only yield the rest args with numeric indices
            yield from array_reverse(
                array_filter($args, fn(int|string $key): bool => is_int($key), ARRAY_FILTER_USE_KEY)
            );
        };
    }

    private function defaultResolver(): callable
    {
        // TODO
        return function (ReflectionParameter $param, Container $container, callable $staticArgProvider): mixed {
            // check the "skip" hint, use static argument

            // check if there is a wire hint, try to fetch the service
            // attempt to construct the service if hinted so

            // in a loop for all possible type-hinted classes,
            // try to fetch the service
            // attempt to construct the class if hinted so

            // if the type hint is a built-in type or there are no more type-hinted classes,
            // try to use a static argument from the pool

            // fall back to the default value for the parameter, if any

            // return null for nullable parameters

            // throw otherwise
        };
    }

    /**
     * @derpecated TODO remove ?
     *
     * A helper method.
     * For each service identifier calls the service provider.
     * If an identifier is `null`, one of the static arguments is used instead.
     *
     * The resulting array might be a mix of services fetched from the service container via the provider
     * and other values passed in as static arguments.
     *
     * @param array $identifiers array of (nullable string) service identifiers
     * @param callable $serviceProvider returns requested services; function(string $identifier): object
     * @param array $staticArguments
     * @return array
     */
    public static function resolveServicesFillingInStaticArguments(
        array $identifiers,
        callable $serviceProvider,
        array $staticArguments
    ): array {
        $services = [];
        if (count($identifiers) > 0) {
            $services = array_map(function ($identifier) use ($serviceProvider, &$staticArguments) {
                if ($identifier !== null) {
                    return $serviceProvider($identifier);
                }
                if (count($staticArguments) > 0) {
                    return array_shift($staticArguments);
                }
                // when no static argument is present for an identifier, return null
                return null;
            }, $identifiers);
        }
        // merge with the rest of the static arguments
        return array_merge(array_values($services), array_values($staticArguments));
    }
}
