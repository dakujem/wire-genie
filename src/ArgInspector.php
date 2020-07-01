<?php

declare(strict_types=1);

namespace Dakujem;

use Psr\Container\ContainerInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract as FunctionRef;
use ReflectionParameter as ParamRef;

/**
 * ArgInspector
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class ArgInspector
{
    /**
     * This resolver will try to automatically detect type-hinted class names of parameters.
     * A custom detector may be used instead. For parameters where no type is detected,
     * it will try to fill in _values_ one by one as passed to the `WireGenie::wire($res, ...$staticArguments)` call.
     *
     * Usage:
     *  $wireGenie->wire(ArgInspector::resolver())->invoke($func)
     *  $wireGenie->wire(ArgInspector::resolver(ArgInspector::tagReader()))->invoke($func)
     *  $wireGenie->wire(ArgInspector::resolver(), 42, 'foobar')->invoke($func)
     *
     * @param callable|null $detector called for every parameter, if present
     * @param callable|null $serviceFetcher fetches a service from a service container
     * @return callable
     */
    public static function resolver(?callable $detector = null, ?callable $serviceFetcher = null): callable
    {
        return function (
            // by default these would be "dependencies", but are used as arguments by this particular resolver
            array $staticArguments,
            ContainerInterface $container,
            callable $target
        ) use ($detector, $serviceFetcher): array {
            $identifiers = static::detectTypes(new ReflectionFunction($target), $detector);
            if (count($identifiers) > 0) {
                $getter = $serviceFetcher === null ? function ($id) use ($container) {
                    return $container->has($id) ? $container->get($id) : null;
                } : function ($id) use ($container, $serviceFetcher) {
                    return call_user_func($serviceFetcher, $id, $container);
                };
                return static::resolveServicesFillingInStaticArguments($identifiers, $getter, $staticArguments);
            }
            return $staticArguments;
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
     * @param callable|null $detector called for each parameter, if provided
     * @return string[] the array may contain null values
     */
    public static function detectTypes(FunctionRef $reflection, ?callable $detector = null): array
    {
        return array_map(function (ParamRef $parameter) use ($reflection, $detector): ?string {
            return $detector !== null ? call_user_func($detector, $parameter, $reflection) : $parameter->getClass()->name;
        }, $reflection->getParameters());
    }

    /**
     * Returns a callable to be used as $detector argument to the `ArgInspector::detectTypes()` call.
     * @see ArgInspector::detectTypes()
     *
     * The callable will collect "tags" of parameter annotations, `@ param`.
     * If no tag is present, it will return type-hinted class name.
     * The tag is in the following form, where `<identifier>` is replaced by the actual service identifier:
     *  [wire:<identifier>]
     * By default the tag looks like the following:
     *  @.param Foobar $foo description [wire:my_service_identifier]
     *
     * Usage:
     *  $types = ArgInspector::types(new ReflectionFunction($func), ArgInspector::tagReader());
     *
     * @param string $tag defaults to "wire"; only alphanumeric characters should be used; case insensitive
     * @param bool $defaultToTypeHint whether or not to return type-hinted class names when a tag is not present
     * @return callable
     */
    public static function tagReader(string $tag = null, bool $defaultToTypeHint = true): callable
    {
        $annotations = null; // cache used for subsequent calls
        return function (
            ParamRef $param,
            FunctionRef $reflection
        ) use ($tag, &$annotations, $defaultToTypeHint): ?string {
            if ($annotations === null) {
                $dc = $reflection->getDocComment();
                $annotations = [];
                if ($dc !== false && trim($dc) !== '') {
                    $m = [];
                    // modifiers: m - multiline; i - case insensitive
                    $regexp = '#@param\W+(.*?\W+)?\$([a-z0-9_]+)(.+?\[' . ($tag ?? 'wire') . ':(.+?)\])?.*?$#mi';
                    //                             $\__________/                               \___/
                    //                           [2] parameter name                    [4] "wire" tag's value
                    preg_match_all($regexp, $dc, $m);
                    foreach ($m[2] as $i => $name) {
                        // [param_name => tag_value] map
                        $annotations[$name] = trim($m[4][$i]);
                    }
                }
            }
            return $annotations[$param->getName()] ?? ($defaultToTypeHint ? $param->getClass()->name : null);
        };
    }

    /**
     * A helper method.
     * For each service identifier calls the service provider.
     * If the identifier is `null`, it uses the static arguments instead.
     *
     * The resulting array might be a mix of services fetched from the service container via the provider
     * and other values passed in as static arguments.
     *
     * @param array $identifiers
     * @param callable $serviceProvider
     * @param array $staticArguments
     * @return array
     */
    public static function resolveServicesFillingInStaticArguments(
        array $identifiers,
        callable $serviceProvider,
        array $staticArguments
    ): array {
        $services = [];
        if (count($identifiers) > 0) {
            $services = array_map(function ($id) use ($serviceProvider, &$staticArguments) {
                if ($id !== null) {
                    return call_user_func($serviceProvider, $id);
                }
                if (count($staticArguments) > 0) {
                    return array_shift($staticArguments);
                }
                // when no static argument is present for an identifier, return null
                return null;
            }, $identifiers);
        }
        // merge with the rest of the static arguments
        return array_merge(array_values($services), array_values($staticArguments));
    }
}
