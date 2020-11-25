<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Magic Lamp. If you rub it thoroughly, something might come out.
 *
 * Wire known dependencies to a callable.
 *
 * Usage:
 *   $factoryFunction = function( ...dependencies... ){
 *       // do stuff or create stuff
 *       return new Service( ... );
 *   };
 *
 *   $lamp = new Lamp( $serviceContainer ); // or use Limiter to limit access to certain services only
 *
 *   // an identifier may be a string key or a class name, depending on your container implementation
 *   $invoker = $lamp->provide( ...dependency-identifier-list... );
 *
 *   $service = $invoker->invoke($factoryFunction); // then invoke the factory like this,
 *   $service = $invoker($factoryFunction);         // or like this
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class Lamp
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
     * @return Provider callable
     */
    public function provide(...$dependencies): callable
    {
        $resolved = array_map(function (string $dep) {
            return $this->container->has($dep) ? $this->container->get($dep) : null;
        }, $dependencies);

        return new Provider(...$resolved);
    }

    /**
     * Same as `provide`, except an exception is thrown when the container can not or will not resolve a dependency.
     *
     * @param mixed ...$dependencies list of identifiers for the container
     * @return Provider callable
     */
    public function provideStrict(...$dependencies): callable
    {
        $resolved = array_map(function (string $dep) {
            return $this->container->get($dep); // will throw if the dependency is not present
        }, $dependencies);

        return new Provider(...$resolved);
    }

    /**
     * Same as `provide`, except it does not throw any container-related exceptions.
     * Dependencies that can not or will not be resolved by the container are resolved to null.
     *
     * @param mixed ...$dependencies list of identifiers for the container
     * @return Provider callable
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

        return new Provider(...$resolved);
    }

    /**
     * Exposes the internal container to a callable.
     * A public getter for the container instance is not provided by design.
     *
     * @param callable $worker function(ContainerInterface $container)
     * @return mixed forwards the return value of the callable
     */
    public function exposeContainer(callable $worker)
    {
        return $worker($this->container, $this);
    }
}
