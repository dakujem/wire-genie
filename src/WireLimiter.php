<?php

declare(strict_types=1);

namespace Dakujem;

use Psr\Container\ContainerInterface;

/**
 * A container wrapper that limits access to whitelisted class instances only.
 *
 * Usage:
 *   $limitedContainer = new WireLimiter( $fullContainer, [ WhitelistOnlyThisInterface::class ] );
 *   new WireGenie( $limitedContainer );
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class WireLimiter implements ContainerInterface
{
    private $container;
    private $whitelist;

    /**
     * @param ContainerInterface $container main container to delegate the calls to
     * @param string[] $whitelist list of allowed class names
     */
    public function __construct(ContainerInterface $container, array $whitelist)
    {
        $this->container = $container;
        $this->whitelist = $whitelist;
    }

    public function has($id): bool
    {
        return $this->container->has($id);
    }

    /**
     * @throws WireLimiterException
     */
    public function get($id)
    {
        $dep = $this->container->get($id);
        foreach ($this->whitelist as $className) {
            if ($dep instanceof $className) {
                return $dep;
            }
        }
        throw new WireLimiterException();
    }
}
