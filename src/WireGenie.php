<?php

namespace Dakujem;

use Psr\Container\ContainerInterface;

/**
 * WireGenie
 */
final class WireGenie
{
    /** @var psr */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function provide(...$args): callable
    {
        $resolved = array_map(function (string $dep) {
            return $this->container->has($dep) ? $this->container->get($dep) : null; // throw instead?
        }, $args);

        return new class($resolved) {
            private $callArgs;

            public function __construct(array $callArgs)
            {
                $this->callArgs = $callArgs;
            }

            public function __invoke(callable $target)
            {
                return $this->call($target);
            }

            public function call(callable $target)
            {
                return call_user_func($target, ...$this->callArgs);
            }
        };
    }
}
