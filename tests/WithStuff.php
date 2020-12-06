<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

/**
 * WithStuff
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
trait WithStuff
{
    /**
     * Invokes a Closure and binds $this to the object given via the first parameter.
     *
     * @param string|object $objectOrClass
     * @param \Closure $closure
     * @return mixed
     */
    protected static function with($objectOrClass, \Closure $closure) //: mixed
    {
        return $closure->bindTo(is_object($objectOrClass) ? $objectOrClass : null, $objectOrClass)();
    }
}
