<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract as FunctionRef;
use ReflectionMethod;

/**
 * Argument Inspector.
 *
 * Allows for reflection-based parameter type detection.
 * Combined with WireInvoker, allows for automatic dependency resolution and injection.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class Inspector
{
    /**
     * Returns a reflection of a callable or a reflection of a class constructor (if present).
     *
     * @param callable|string $target a callable or a class name
     * @return FunctionRef|null
     * @throws ReflectionException
     */
    public static function reflectionOf($target): ?FunctionRef
    {
        return is_string($target) && class_exists($target) ?
            static::reflectionOfConstructor($target) :
            static::reflectionOfCallable($target);
    }

    /**
     * Return a reflection of a callable for type detection or other uses.
     *
     * @param callable $callable any valid callable (closure, invokable object, string, array)
     * @return FunctionRef
     * @throws ReflectionException
     */
    public static function reflectionOfCallable(callable $callable): FunctionRef
    {
        if ($callable instanceof Closure) {
            return new ReflectionFunction($callable);
        }
        if (is_string($callable)) {
            $pcs = explode('::', $callable);
            return count($pcs) > 1 ? new ReflectionMethod($pcs[0], $pcs[1]) : new ReflectionFunction($callable);
        }
        if (!is_array($callable)) {
            $callable = [$callable, '__invoke'];
        }
        return new ReflectionMethod($callable[0], $callable[1]);
    }

    /**
     * Return a reflection of the constructor of the class for type detection or other uses.
     * If the class has no constructor, null is returned.
     *
     * @param string $className a class name
     * @return FunctionRef|null
     * @throws ReflectionException
     */
    public static function reflectionOfConstructor(string $className): ?FunctionRef
    {
        return (new ReflectionClass($className))->getConstructor();
    }
}
