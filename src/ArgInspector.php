<?php

declare(strict_types=1);

namespace Dakujem;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract as FunctionRef;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter as ParamRef;
use ReflectionUnionType;

/**
 * Argument Inspector.
 *
 * Allows for reflection-based parameter type detection.
 * Combined with WireInvoker, allows for automatic dependency resolution and injection.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class ArgInspector
{
    /**
     * Returns a reflection-based detector that detects parameter types.
     * Optionally may use other detection for individual parameters, like "wire tag" detection.
     *
     * Usage:
     *  new WireInvoker($container, ArgInspector::typeDetector(ArgInspector::tagReader()))
     *
     * @param callable|null $paramDetector optional detector used for individual parameters
     * @return callable
     */
    public static function typeDetector(?callable $paramDetector = null): callable
    {
        return function (?FunctionRef $reflection) use ($paramDetector): array {
            return $reflection !== null ? static::detectTypes($reflection, $paramDetector) : [];
        };
    }

    /**
     * Returns a reflection-based detector that only detects "wire tags".
     *
     * Usage:
     *  new WireInvoker($container, ArgInspector::tagDetector())
     *
     * @param string $tag defaults to "wire"; only alphanumeric characters should be used; case insensitive
     * @return callable
     */
    public static function tagDetector(string $tag = null): callable
    {
        return static::typeDetector(static::tagReader($tag, false));
    }

    /**
     * Returns a callable to be used as $detector argument to the `ArgInspector::detectTypes()` call.
     * @see ArgInspector::detectTypes()
     *
     * The callable will collect "wire tags" of parameter annotations, `@ param`.
     * If no tag is present, it will return type-hinted class name by default.
     *
     * A "wire tag" is in the following form, where `<identifier>` is replaced by the actual service identifier:
     *  [wire:<identifier>]
     * By default a "wire tag" looks like the following:
     *  @.param Foobar $foo description [wire:my_service_identifier]
     *                                  \__________________________/
     *                                      the whole wire tag
     *
     *  @.param Foobar $foo description [wire:my_service_identifier]
     *                                        \___________________/
     *                                          service identifier
     * Usage:
     *  $types = ArgInspector::detectTypes(new ReflectionFunction($func), ArgInspector::tagReader());
     *
     * @param string $tag defaults to "wire"; only alphanumeric characters should be used; case insensitive
     * @param bool $defaultToTypeHint whether or not to return type-hinted class names when a tag is not present
     * @return callable
     */
    public static function tagReader(string $tag = null, bool $defaultToTypeHint = true): callable
    {
        $annotations = null; //          Cache used for subsequent calls...
        $reflectionInstance = null; //   ...with the same reflection instance.
        return function (
            ParamRef $param,
            FunctionRef $reflection
        ) use ($tag, &$annotations, &$reflectionInstance, $defaultToTypeHint): ?string {
            if ($annotations === null || $reflection !== $reflectionInstance) {
                $reflectionInstance = $reflection;
                $annotations = static::parseWireTags($reflection, $tag);
            }
            $annotation = $annotations[$param->getName()] ?? ($defaultToTypeHint ? static::typeHintOf($param) : null);
            // omit empty annotations - empty wire tag indicates "no wiring"
            return $annotation !== '' ? $annotation : null;
        };
    }

    /**
     * Returns an array of type-hinted argument class names by default.
     *
     * If a custom $detector is passed, it is used for each of the parameters instead.
     *
     * Usage:
     *  $types = ArgInspector::types(new ReflectionFunction($func));
     *  $types = ArgInspector::types(new ReflectionMethod($object, $methodName));
     *  $types = ArgInspector::types(new ReflectionMethod('Namespace\Object::method'));
     *
     * @param FunctionRef $reflection
     * @param callable|null $paramDetector called for each parameter, if provided
     * @param bool $removeTrailingNullValues null values at the end of the returned array will be omitted by default
     * @return string[] the array may contain null values
     */
    public static function detectTypes(
        FunctionRef $reflection,
        ?callable $paramDetector = null,
        bool $removeTrailingNullValues = true
    ): array {
        $types = array_map(function (ParamRef $parameter) use ($reflection, $paramDetector): ?string {
            return $paramDetector !== null ?
                $paramDetector($parameter, $reflection) :
                static::typeHintOf($parameter);
        }, $reflection->getParameters());

        // remove trailing null values (helps with variadic parameters and so on)
        while ($removeTrailingNullValues && count($types) > 0 && end($types) === null) {
            array_pop($types);
        }
        return $types;
    }

    /**
     * Returns a reflection of a callable or a reflection of a class name (if a constructor is present).
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

    private static function typeHintOf(ParamRef $parameter): ?string
    {
        $typeRef = $parameter->getType();
        if ($typeRef instanceof ReflectionUnionType) {
            // Note for PHP 8.0+: only the first type hint is used when a union type is hinted.
            $typeRef = $typeRef->getTypes()[0] ?? null;
        }
        return $typeRef instanceof ReflectionNamedType && !$typeRef->isBuiltin()
            ? $typeRef->getName()
            : null;
    }

    /**
     * @internal
     */
    public static function parseWireTags(FunctionRef $reflection, string $tag = null): array
    {
        $annotations = [];
        $dc = $reflection->getDocComment();
        if ($dc !== false && trim($dc) !== '') {
            $m = [];
            // modifiers: m - multiline; i - case insensitive
            $regexp = '#@param\W+(.*?\W+)?\$([a-z0-9_]+)(.+?\[' . ($tag ?? 'wire') . ':(.*?)\])?.*?$#mi';
            //                             $\__________/                               \___/
            //                           [2] parameter name                    [4] service identifier
            preg_match_all($regexp, $dc, $m);
            foreach ($m[2] as $i => $name) {
                // [param_name => tag_value] map
                $annotations[$name] = $m[3][$i] !== '' ? trim($m[4][$i]) : null; // only when a tag is present
            }
        }
        return $annotations;
    }
}
