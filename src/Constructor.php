<?php

declare(strict_types=1);

namespace Dakujem\Wire;

/**
 * A general interface for objects able to create instances of other classes.
 * These implementations will usually provide some or all arguments to the constructor call.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
interface Constructor
{
    /**
     * Creates an instance of a class.
     * Arguments provided to the constructor differ according to implementation.
     *
     * @param string $target name of a class to be instantiated
     * @return mixed an instance of the target class
     */
    public function construct(string $target): mixed;
}
