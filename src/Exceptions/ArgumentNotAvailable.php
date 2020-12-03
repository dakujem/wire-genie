<?php

declare(strict_types=1);

namespace Dakujem\Wire\Exceptions;

use RuntimeException;

/**
 * ArgumentNotAvailable
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class ArgumentNotAvailable extends RuntimeException implements Unresolvable
{
    public ?string $name = null;

    public static function arg(?string $name)
    {
        $e = new static($name !== null ? "Unavailable: '{$name}'." : 'No argument available.');
        $e->name = $name;
        return $e;
    }
}
