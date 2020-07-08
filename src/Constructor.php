<?php

declare(strict_types=1);

namespace Dakujem;

/**
 * A general interface for an object able to create instances of other classes.
 * These implementations will usually provide arguments to the constructor call.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
interface Constructor
{
    /**
     * Creates an instance of a class.
     *
     * @param string $target class name of a class to be instantiated
     * @return mixed an instance of the target class
     */
    public function construct(string $target);
}
