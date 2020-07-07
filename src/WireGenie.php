<?php

declare(strict_types=1);

namespace Dakujem;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * WireGenie - wire dependencies to a callable.
 *
 * Usage:
 *   $factoryFunction = function( ...dependencies... ){
 *       // do stuff or create stuff
 *       return new Service( ... );
 *   };
 *
 *   $genie = new WireGenie( $serviceContainer ); // or use WireLimiter to limit access to certain services only
 *
 *   // an identifier may be a string key or a class name, depending on your container implementation
 *   $invoker = $genie->provide( ...dependency-identifier-list... );
 *
 *   $service = $invoker->invoke($factoryFunction); // then invoke the factory like this,
 *   $service = $invoker($factoryFunction);         // or like this
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class WireGenie
{
    use PredictableAccess;

    /** @var ContainerInterface */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Resolves given dependencies using the container and returns an invoker.
     * The invoker can be used to invoke other callables with the resolved dependencies.
     *
     * When a dependency is not present in the container, it is resolved to null instead.
     * Alternatively, you can use `provideStrict` or `provideSafe` for different behaviour.
     *
     * @param mixed ...$dependencies list of identifiers for the container
     * @return InvokableProvider callable
     */
    public function provide(...$dependencies): callable
    {
        $resolved = array_map(function (string $dep) {
            return $this->container->has($dep) ? $this->container->get($dep) : null;
        }, $dependencies);

        return new InvokableProvider(...$resolved);
    }

    /**
     * Same as `provide`, except an exception is thrown when the container can not or will not resolve a dependency.
     *
     * @param mixed ...$dependencies list of identifiers for the container
     * @return InvokableProvider callable
     */
    public function provideStrict(...$dependencies): callable
    {
        $resolved = array_map(function (string $dep) {
            return $this->container->get($dep); // will throw if the dependency is not present
        }, $dependencies);

        return new InvokableProvider(...$resolved);
    }

    /**
     * Same as `provide`, except it does not throw any container-related exceptions.
     * Dependencies that can not or will not be resolved by the container are resolved to null.
     *
     * @param mixed ...$dependencies list of identifiers for the container
     * @return InvokableProvider callable
     */
    public function provideSafe(...$dependencies): callable
    {
        $resolved = array_map(function (string $dep) {
            try {
                return $this->container->get($dep);
            } catch (ContainerExceptionInterface $e) {
                return null;
            }
        }, $dependencies);

        return new InvokableProvider(...$resolved);
    }

    /**
     * Employing a resolver, returns an invoker that can be used to invoke callables.
     * Invocation arguments are resolved using the resolver for each invocation at the moment of invocation,
     * as opposed to the `provide*` methods.
     *
     * The resolver is passed the $dependencies as the first argument, container of the WireGenie instance
     * and the callable being invoked.
     *
     * Example: A basic resolver that mimics the `WireGenie::provide` method might look like the following:
     *  function(array $deps, $container): array {
     *      return array_map(function($dep) use ($container) {
     *          return $container->has($dep) ? $container->get($dep) : null;
     *      }, $deps);
     *  }
     *
     * @param callable $resolver a resolver that returns an array of invocation arguments;
     *                           signature function(array $dependencies, ContainerInterface $c, callable $target): array
     * @param mixed ...$dependencies list of identifiers for the container; or any other arguments usable by the resolver
     * @return DormantProvider callable
     */
    public function employ(callable $resolver, ...$dependencies): callable
    {
        $deferredResolver = function (callable $target, iterable $staticArgs = []) use ($resolver, $dependencies) {
            // The resolver will be called to resolve the arguments.
            // The dependencies, a container and the target will be passed to the call,
            // which allows for advanced techniques to be implemented in uniform manner.
            return call_user_func($resolver, $dependencies, $this->container, $target, $staticArgs);
        };
        return new DormantProvider($deferredResolver);
    }

    /**
     * Exposes the internal container to a callable.
     * A public getter for the container instance is not provided by design.
     *
     * @param callable $operator function(ContainerInterface $container)
     * @return mixed forwards the return value of the callable
     */
    public function exposeTo(callable $operator)
    {
        return call_user_func($operator, $this->container, $this);
    }
}
