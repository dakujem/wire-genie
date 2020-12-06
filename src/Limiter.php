<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Dakujem\Wire\Exceptions\ServiceNotWhitelisted;
use Psr\Container\ContainerInterface as Container;

/**
 * A container wrapper that limits access to whitelisted class instances only.
 *
 * Usage:
 *   $limitedContainer = new Limiter( $fullContainer, [ WhitelistOnlyThisInterface::class ] );
 *   new Genie( $limitedContainer );
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class Limiter implements Container
{
    use PredictableAccess;

    /**
     * @var Container
     */
    private $container;
    /**
     * @var iterable|string[]
     */
    private $whitelist;

    /**
     * @param Container $container main container to delegate the calls to
     * @param string[] $whitelist list of allowed class names
     */
    public function __construct(
        Container $container,
        iterable $whitelist
    ) {
        $this->container = $container;
        $this->whitelist = $whitelist;
    }

    public function has($id): bool
    {
        return $this->container->has($id);
    }

    /**
     * @throws ServiceNotWhitelisted
     */
    public function get($id)
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
