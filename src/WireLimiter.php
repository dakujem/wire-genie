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
    use PredictableAccess;

    /**
     * @param ContainerInterface $container main container to delegate the calls to
     * @param string[] $whitelist list of allowed class names
     */
    public function __construct(
        private ContainerInterface $container,
        private iterable $whitelist
    ) {
    }

    public function has($id): bool
    {
        return $this->container->has($id);
    }

    /**
     * @throws WireLimiterException
     */
    public function get($id): mixed
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
