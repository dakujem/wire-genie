<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Psr\Container\ContainerInterface as Container;

/**
 * The Wonderful Lamp 🪔 - if you rub it thoroughly, something might come out...
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class Lamp
{
    use PredictableAccess;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var callable|null
     */
    private $core;

    public function __construct(
        Container $container,
        ?callable $core = null
    ) {
        $this->core = $core;
        $this->container = $container;
    }

    /**
     * @return Genie 🧞
     */
    public function rub(): Genie
    {
        return new Genie($this->container, $this->core);
    }
}
