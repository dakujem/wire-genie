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
 *   $invokableProvider = $genie->provide( ...dependency-identifier-list... );
 *
 *   $service = $invokableProvider->invoke($factoryFunction); // then invoke the factory like this,
 *   $service = $invokableProvider($factoryFunction);         // or like this
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
     * Resolves given dependencies using the container and returns a callable provider.
     * The provider can be used to invoke other callables with the resolved dependencies.
     *
     * When the container does not have a dependency, it is resolved to null instead.
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
     * Returns a provider that can be used to invoke callables.
     * Invocation arguments are resolved using the resolver for each invocation,
     * as opposed to the `provide*` methods.
     *
     * The resolver is passed the dependencies, container and the target callable.
     *
     * A basic resolver that mimics the `WireGenie::provide` method might look like the following:
     *  function(array $deps, $container): array {
     *      return array_map(function($dep) use ($container) {
     *          return $container->has($dep) ? $container->get($dep) : null;
     *      }, $deps);
     *  }
     *
     * @param callable $resolver a resolver that returns an array of call arguments;
     *                           signature function(array $deps, ContainerInterface $c, callable $target): array
     * @param mixed ...$dependencies list of identifiers for the container
     * @return DormantProvider callable
     */
    public function wire(callable $resolver, ...$dependencies): callable
    {
        $deferredResolver = function (callable $target) use ($resolver, $dependencies) {
            // The resolver will be called to resolve the arguments.
            // The to the resolver will be passed the dependencies, container and the target,
            // which allows for advanced techniques to be implemented in uniform manner.
            return call_user_func($resolver, $dependencies, $this->container, $target);
        };
        return new DormantProvider($deferredResolver);
    }
}
