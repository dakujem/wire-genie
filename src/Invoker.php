<?php

namespace Dakujem;

/**
 * A general interface for an object able to invoke callables.
 * These implementations will usually provide arguments to the call.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
interface Invoker
{
    /**
     * Invokes a callable.
     * Returns the result of the call.
     * Arguments provided to the call differ according to implementation.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target);
}
