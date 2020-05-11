<?php

namespace Dakujem;

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
     * @return callable
     */
    public function provide(...$dependencies): callable
    {
        $resolved = array_map(function (string $dep) {
            return $this->container->has($dep) ? $this->container->get($dep) : null;
        }, $dependencies);

        return new InvokableProvider($resolved);
    }

    /**
     * Same as `provide`, except an exception is thrown when the container can not or will not resolve a dependency.
     *
     * @param mixed ...$dependencies list of identifiers for the container
     * @return callable
     */
    public function provideStrict(...$dependencies): callable
    {
        $resolved = array_map(function (string $dep) {
            return $this->container->get($dep); // will throw if the dependency is not present
        }, $dependencies);

        return new InvokableProvider($resolved);
    }

    /**
     * Same as `provide`, except it does not throw any container-related exceptions.
     * Dependencies that can not or will not be resolved by the container are resolved to null.
     *
     * @param mixed ...$dependencies list of identifiers for the container
     * @return callable
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

        return new InvokableProvider($resolved);
    }
}
