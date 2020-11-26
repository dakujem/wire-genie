<?php

declare(strict_types=1);

namespace Dakujem\Wire;

/**
 * Wonderful Magic Lamp - if you rub it thoroughly, something might come out...
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
final class Lamp
{

    /**
     * Depending on how this is called,
     *
     * @param mixed ...$args
     * @return Genie|Provider callable
     */
    public function rub(...$args): callable
    {
        if ($args === []) {
            return new Genie($this->container);
        }
        return (new EagerGenie($this->container))->provide(...$args);
    }
}
