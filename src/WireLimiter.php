<?php

namespace Dakujem;

use Psr\Container\ContainerInterface;

final class WireLimiter implements ContainerInterface
{
    private $container;
    private $whitelist;

    public function __construct(ContainerInterface $container, array $whitelist)
    {
        $this->container = $container;
        $this->whitelist = $whitelist;
    }

    public function has(string $id): bool
    {
        return $this->container->has($id);
    }

    public function get(string $id)
    {
        $dep = $this->container->get($id);
        foreach ($this->whitelist as $className) {
            if ($dep instanceof $className) {
                return $dep;
            }
        }
        throw new LimiterException();
    }
}
