<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Dakujem\ArgInspector;
use Psr\Container\ContainerInterface as Container;
use ReflectionException;
use ReflectionFunctionAbstract as FunctionRef;
use ReflectionNamedType;
use ReflectionParameter as ParamRef;
use ReflectionUnionType;

/**
 * A resolver strategy composed of a detector, reflector and a service proxy.
 * By default, uses reflection and "wire tags".
 *
 * Callable.
 * fn(Genie, callable|string, ...):iterable
 *
 * This strategy has been ported from the previous implementation used in WireInvoker class.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class TagBasedStrategy
{
    use PredictableAccess;

    /**
     * A callable that allows to customize the way service identifiers are detected.
     * @var callable function(ReflectionFunctionAbstract $reflection): string[]
     */
    private $detector;

    /**
     * A callable that allows to customize the way services are fetched from a container.
     * @var callable function(string $identifier, ContainerInterface $container): service
     */
    private $serviceProxy;

    /**
     * A callable that allows to customize the way a function reflection is acquired.
     * @var callable function($target): FunctionReflectionAbstract
     */
    private $reflector;

    /**
     * Construct an instance of WireInvoker. Really? Yup!
     *
     * Detector, reflector and service proxy work as a pipeline to provide a service for a target's parameter:
     *      $service = $serviceProxy( $detector( $reflector( $target ) ) )
     *
     * In theory, the whole pipeline can be altered not to work with reflections,
     * there are no restriction to return types of the three callables, except for the detector.
     *
     * @param callable|null $detector a callable used for identifier detection;
     *                                takes the result of $reflector, MUST return an array of service identifiers;
     *                                function(ReflectionFunctionAbstract $reflection): string[]
     * @param callable|null $serviceProxy a callable that takes a service identifier and a container instance
     *                                    and SHOULD return the requested service;
     *                                    function(string $identifier, ContainerInterface $container): service
     * @param callable|null $reflector a callable used to get the reflection of the target being invoked or constructed;
     *                                 SHOULD return a reflection of the function or constructor that will be invoked;
     *                                 function($target): FunctionReflectionAbstract
     */
    public function __construct(
        ?callable $detector = null,
        ?callable $serviceProxy = null,
        ?callable $reflector = null
    ) {
        $this->detector = $detector;
        $this->reflector = $reflector;
        $this->serviceProxy = $serviceProxy;
    }

    public function __invoke(
        Genie $genie,
        callable|string $target,
        ...$staticArgs,
    ): iterable {
        return $this->resolveArguments(
            $genie->exposeContainer(function ($c): Container {
                return $c;
            }),
            $target,
            ...$staticArgs,
        );
    }

    /**
     * Works sort of as a pipeline:
     *  $target -> $reflector -> $detector -> serviceProvider => service
     *  or $serviceProvider($detector($reflector($target)))
     *
     * @param Container $container
     * @param callable|string $target a callable to be invoked or a name of a class to be constructed
     * @param mixed ...$staticArguments static arguments to fill in for parameters where identifier can not be detected
     * @return iterable
     */
    private function resolveArguments(Container $container, $target, ...$staticArguments): iterable
    {
        $reflection = ($this->reflector ?? ArgInspector::class . '::reflectionOf')($target);
        $identifiers = ($this->detector ?? ArgInspector::typeDetector(ArgInspector::tagReader()))($reflection);
        if (count($identifiers) > 0) {
            return self::resolveServicesFillingInStaticArguments(
                $identifiers,
                self::makeProvider($this->serviceProxy, $container),
                $staticArguments
            );
        }
        return $staticArguments;
    }

    private static function makeProvider(?callable $serviceProxy, Container $container): callable
    {
        return $serviceProxy === null ? function ($id) use ($container) {
            return $container->has($id) ? $container->get($id) : null;
        } : function ($id) use ($container, $serviceProxy) {
            return $serviceProxy($id, $container);
        };
    }

    /**
     * A helper method.
     * For each service identifier calls the service provider.
     * If an identifier is `null`, one of the static arguments is used instead.
     *
     * The resulting array might be a mix of services fetched from the service container via the provider
     * and other values passed in as static arguments.
     *
     * @param array $identifiers array of (nullable string) service identifiers
     * @param callable $serviceProvider returns requested services; function(string $identifier): object
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
            $services = array_map(function ($identifier) use ($serviceProvider, &$staticArguments) {
                if ($identifier !== null) {
                    return $serviceProvider($identifier);
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

    /**
     * Returns a reflection-based detector that detects parameter types.
     * Optionally may use other detection for individual parameters, like attribute detection.
     *
     * Usage:
     *  new WireInvoker($container, ArgInspector::typeDetector(ArgInspector::attributeReader()))
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
     * @deprecated switch to using attributes for much better performance.
     *
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
     * @deprecated switch to using attributes for much better performance.
     *
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
    public static function reflectionOf(callable|string $target): ?FunctionRef
    {
        return Inspector::reflectionOf($target);
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
        return Inspector::reflectionOfCallable($callable);
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
        return Inspector::reflectionOfConstructor($className);
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
     * @deprecated switch to using attributes for much better performance.
     *
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
