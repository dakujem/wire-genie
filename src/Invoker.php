<?php

declare(strict_types=1);

namespace Dakujem\Wire;

/**
 * A general interface for objects able to invoke callables.
 * These implementations will usually provide some or all arguments for the invocation.
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
    public function invoke(callable $target): mixed;
}
