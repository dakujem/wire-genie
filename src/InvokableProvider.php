<?php

declare(strict_types=1);

namespace Dakujem;

/**
 * A provider returned by WireGenie::provide method(s).
 * The instances are _callable_.
 *
 * All the call arguments will have been resolved at the moment of creating the instance.
 *
 * Usage:
 *   $invokableProvider = (new WireGenie( ... ))->provide( ... );
 *   $invokableProvider->invoke(function( ... ){
 *       // do stuff or create stuff
 *   });
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
class InvokableProvider implements Invoker
{
    use PredictableAccess;

    private $callArgs;

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
    public function invoke(callable $target)
    {
        return call_user_func($target, ...$this->callArgs);
    }

    /**
     * This provider instances are also callable.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function __invoke(callable $target)
    {
        return $this->invoke($target);
    }
}
