<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use LogicException;

/**
 * A trait to disable magic access to undeclared properties,
 * which is the default PHP behaviour, but easily leads to bugs.
 *
 * This trait is usable for any value objects or objects prone to unintentional attribute access.
 *
 * Note that to avoid WTF?! moments when defining __get or __set method,
 * one should also define __isset and __unset, which prevents issues when using ?? operator or isset
 * with arrays for example.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
trait PredictableAccess
{
    public function __get($name)
    {
        throw new LogicException(sprintf('Invalid read of property \'%s::$%s\'.', static::class, $name));
    }

    public function __set($name, $value)
    {
        throw new LogicException(sprintf('Invalid write to property \'%s::$%s\'.', static::class, $name));
    }

    public function __isset($name)
    {
        throw new LogicException(sprintf('Invalid read of property \'%s::$%s\'.', static::class, $name));
    }

    public function __unset($name)
    {
        throw new LogicException(sprintf('Invalid write to property \'%s::$%s\'.', static::class, $name));
    }
}
