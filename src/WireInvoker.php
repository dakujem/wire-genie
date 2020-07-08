<?php

declare(strict_types=1);

namespace Dakujem;

use Psr\Container\ContainerInterface;

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

    /** @var callable */
    private $detector;
    /** @var callable */
    private $serviceProvider;
    /** @var callable */
    private $reflector;

    /**
     * Construct an instance of WireInvoker. Really? Yup!
     *
     * Detector, reflector and service proxy work as a pipeline to provide a service for a target's parameter:
     *      $service = $serviceProvider( $detector( $reflector( $target ) ) )
     *
     * @param ContainerInterface $container service container
     * @param callable|null $detector a callable used for identifier detection;
     *                                takes the result of $reflector, returns an array of service identifiers;
     *                                function(ReflectionFunctionAbstract $reflection): string[]
     * @param callable|null $serviceProxy a callable that takes a service identifier and a container instance
     *                                    and returns the requested service;
     *                                    function(string $identifier, ContainerInterface $container): service
     * @param callable|null $reflector a callable used to get the reflection of the target being invoked or constructed;
     *                                 function($target): FunctionReflectionAbstract
     */
    public function __construct(
        ContainerInterface $container,
        ?callable $detector = null,
        ?callable $serviceProxy = null,
        ?callable $reflector = null
    ) {
        $this->detector = $detector;
        $this->reflector = $reflector;
        $this->serviceProvider = $serviceProxy === null ? function ($id) use ($container) {
            return $container->has($id) ? $container->get($id) : null;
        } : function ($id) use ($container, $serviceProxy) {
            return call_user_func($serviceProxy, $id, $container);
        };
    }

    /**
     * Create an instance of WireInvoker using a WireGenie instance's container.
     * Other parameters are the same as for the constructor.
     * @see WireInvoker::__construct()
     *
     * @param WireGenie $wireGenie
     * @param callable|null $detector
     * @param callable|null $serviceProxy
     * @param callable|null $reflector
     * @return static
     */
    public static function consume(
        WireGenie $wireGenie,
        ?callable $detector = null,
        ?callable $serviceProxy = null,
        ?callable $reflector = null
    ): self {
        $f = function (ContainerInterface $container) use ($detector, $serviceProxy, $reflector) {
            return new static($container);
        };
        return $wireGenie->exposeTo($f);
    }

    /**
     * Invokes the callable $target with automatically resolved arguments.
     * Unresolved arguments are filled in from the static argument pool.
     * Returns the result of the call.
     *
     * @param callable $target callable to be invoked
     * @param mixed ...$staticArguments static argument pool
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target, ...$staticArguments)
    {
        $args = $this->resolveArguments($target, ...$staticArguments);
        return call_user_func($target, ...$args);
    }

    /**
     * Constructs the requested object with automatically resolved arguments.
     * Unresolved arguments are filled in from the static argument pool.
     * Returns the constructed instance.
     *
     * @param string $target target class name
     * @param mixed ...$staticArguments static argument pool
     * @return mixed the constructed class instance
     */
    public function construct(string $target, ...$staticArguments)
    {
        $args = $this->resolveArguments($target, ...$staticArguments);
        return new $target(...$args);
    }

    /**
     * Works sort of as a pipeline:
     *  $target -> $reflector -> $detector -> serviceProvider => service
     *  or $serviceProvider($detector($reflector($target)))
     *
     * @param callable|string $target a callable to be invoked or a name of a class to be constructed
     * @param mixed ...$staticArguments static arguments to fill in for parameters where identifier can not be detected
     * @return iterable
     */
    private function resolveArguments($target, ...$staticArguments): iterable
    {
        $reflection = call_user_func($this->reflector ?? ArgInspector::class . '::reflectionOf', $target);
        $identifiers = call_user_func($this->detector ?? ArgInspector::typeDetector(), $reflection);
        if (count($identifiers) > 0) {
            return static::resolveServicesFillingInStaticArguments(
                $identifiers,
                $this->serviceProvider,
                $staticArguments
            );
        }
        return $staticArguments;
    }

    /**
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
                    return call_user_func($serviceProvider, $identifier);
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
