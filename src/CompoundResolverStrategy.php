<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Dakujem\ArgInspector;

/**
 * CompoundResolverStrategy
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class CompoundResolverStrategy
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

//        === null ? function ($id) use ($container) {
//            return $container->has($id) ? $container->get($id) : null;
//        } : function ($id) use ($container, $serviceProxy) {
//            return $serviceProxy($id, $container);
//        };
    }

    /**
     * Works sort of as a pipeline:
     *  $target -> $reflector -> $detector -> serviceProvider => service
     *  or $serviceProvider($detector($reflector($target)))
     *
     * @param callable|string $target a callable to be invoked or a name of a class to be constructed
     * @param mixed ...$staticArguments static arguments to fill in for parameters where identifier can not be detected
     * @return iterable
     */
    private function resolveArguments($target, ...$staticArguments): iterable
    {
        $reflection = ($this->reflector ?? ArgInspector::class . '::reflectionOf')($target);
        $identifiers = ($this->detector ?? ArgInspector::typeDetector(ArgInspector::tagReader()))($reflection);
        if (count($identifiers) > 0) {
            return static::resolveServicesFillingInStaticArguments(
                $identifiers,
                $this->serviceProxy,
                $staticArguments
            );
        }
        return $staticArguments;
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
}
