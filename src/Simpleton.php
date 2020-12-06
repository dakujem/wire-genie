<?php

declare(strict_types=1);

namespace Dakujem\Wire;

/**
 * A simple dependency provider.
 * Provides the same set of arguments for each invocation.
 *
 * Notes:
 *   The instances are _callable_.
 *   All the call arguments will have been resolved at the moment of creating an instance of this class.
 *
 * Usage:
 *   $unit = new Simpleton($service1, $service2);
 *   // or
 *   $unit = (new Genie( ... ))->provide( Service1::class, Service2::class );
 *   // then
 *   $unit->invoke(function( Service1 $service1, Service2 $service2 ){
 *       // do stuff
 *   });
 *   $unit->construct(CompoundService::class);
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
class Simpleton implements Invoker, Constructor
{
    use PredictableAccess;

    /**
     * @var array
     */
    private $callArgs;

    /**
     * @param mixed ...$args arguments that will be passed to callables during the invocation.
     */
    public function __construct(...$args)
    {
        $this->callArgs = $args;
    }

    /**
     * Invokes the callable $target with arguments passed to the constructor of the provider.
     * Returns the result of the call.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target)
    {
        return $target(...$this->callArgs);
    }

    /**
     * @param string $target
     * @return mixed
     */
    public function construct(string $target)
    {
        return new $target(...$this->callArgs);
    }

    /**
     * This provider instances are also callable.
     *
     * @param callable|string $target callable to be invoked or the name of a class to be constructed.
     * @return mixed result of the $target callable invocation or an instance of the requested class
     */
    public function __invoke($target)
    {
        return is_string($target) && class_exists($target) ?
            $this->construct($target) :
            $this->invoke($target);
    }
}
