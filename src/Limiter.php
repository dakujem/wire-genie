<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Dakujem\Wire\Exceptions\ServiceNotWhitelisted;
use Psr\Container\ContainerInterface;

/**
 * A container wrapper that limits access to whitelisted class instances only.
 *
 * Usage:
 *   $limitedContainer = new Limiter( $fullContainer, [ WhitelistOnlyThisInterface::class ] );
 *   new Genie( $limitedContainer );
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class Limiter implements ContainerInterface
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
     * @throws ServiceNotWhitelisted
     */
    public function get($id): mixed
    {
        $dep = $this->container->get($id);
        foreach ($this->whitelist as $className) {
            if ($dep instanceof $className) {
                return $dep;
            }
        }
        throw new ServiceNotWhitelisted();
    }
}
