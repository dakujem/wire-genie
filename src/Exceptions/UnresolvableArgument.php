<?php

declare(strict_types=1);

namespace Dakujem\Wire\Exceptions;

use RuntimeException;

/**
 * UnresolvableArgument
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class UnresolvableArgument extends RuntimeException implements Unresolvable
{
    public static function arg(string $name)
    {
        return new static("Unresolvable: '{$name}'.");
    }
}
