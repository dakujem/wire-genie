<?php

namespace Dakujem;

use Psr\Container\ContainerInterface;

/**
 * WireFactory
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class WireFactory implements Invoker, Constructor
{
    use PredictableAccess;

    /** @var callable */
    private $detector;
    /** @var callable */
    private $serviceProvider;
    /** @var callable */
    private $reflector;

    public function __construct(
        ContainerInterface $container,
        ?callable $detector = null,
        ?callable $serviceProxy = null,
        ?callable $reflector = null
    ) {
        $this->detector = $detector;
        $this->reflector = $reflector;
        $this->serviceProvider = $serviceProxy === null ? function ($id) use ($container) {
            return $container->has($id) ? $container->get($id) : null;
        } : function ($id) use ($container, $serviceProxy) {
            return call_user_func($serviceProxy, $id, $container);
        };
    }

    public static function consume(
        WireGenie $wireGenie,
        ?callable $detector = null,
        ?callable $serviceProxy = null,
        ?callable $reflector = null
    ): self {
        $f = function (ContainerInterface $container) use ($detector, $serviceProxy, $reflector) {
            return new static($container);
        };
        return $wireGenie->exposeTo($f);
    }

    /**
     * Invokes the callable $target with arguments returned by the resolver passed to the constructor.
     * Returns the result of the call.
     *
     * @param callable $target callable to be invoked
     * @param mixed ...$resolverArgs static arguments for the resolver
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target, ...$resolverArgs)
    {
        $args = $this->resolveArguments($target, ...$resolverArgs);
        return call_user_func($target, ...$args);
    }

    public function construct(string $target, ...$resolverArgs)
    {
        $args = $this->resolveArguments($target, ...$resolverArgs);
        return new $target(...$args);
    }

    private function resolveArguments($target, ...$staticArguments): iterable
    {
        $reflection = call_user_func($this->reflector ?? ArgInspector::class . '::reflectionOf', $target);
        $identifiers = call_user_func($this->detector ?? ArgInspector::detector(), $reflection);
        if (count($identifiers) > 0) {
            return static::resolveServicesFillingInStaticArguments(
                $identifiers,
                $this->serviceProvider,
                $staticArguments
            );
        }
        return $staticArguments;
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
