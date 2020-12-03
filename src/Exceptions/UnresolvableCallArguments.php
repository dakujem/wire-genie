<?php

declare(strict_types=1);

namespace Dakujem\Wire\Exceptions;

use RuntimeException;
use Throwable;

/**
 * UnresolvableCallArguments
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class UnresolvableCallArguments extends RuntimeException implements Unresolvable
{
    public static function from(Throwable $previous): self
    {
        return new static(message: $previous->getMessage(), previous: $previous);
    }
}
