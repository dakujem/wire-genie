<?php

declare(strict_types=1);

namespace Dakujem\Wire;

/**
 * A simple static dependency provider.
 * The instances are _callable_.
 *
 * This class is returned by EagerGenie::provide* method(s).
 *
 * All the call arguments will have been resolved at the moment of creating the instance.
 *
 * Usage:
 *   $provider = new Provider($service1, $service2);
 *   // or
 *   $provider = (new EagerGenie( ... ))->provide( Service1::class, Service2::class );
 *   // then
 *   $provider->invoke(function( Service1 $service1, Service2 $service2 ){
 *       // do stuff or create stuff
 *   });
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
class Provider implements Invoker
{
    use PredictableAccess;

    private array $callArgs;

    /**
     * @param mixed ...$callArgs arguments that will be passed to callables during the invocation.
     */
    public function __construct(...$callArgs)
    {
        $this->callArgs = $callArgs;
    }

    /**
     * Invokes the callable $target with arguments passed to the constructor of the provider.
     * Returns the result of the call.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target): mixed
    {
        return $target(...$this->callArgs);
    }

    /**
     * This provider instances are also callable.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function __invoke(callable $target): mixed
    {
        return $this->invoke($target);
    }
}
