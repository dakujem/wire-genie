<?php

namespace Dakujem;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * The access the service is not allowed.
 */
class LimiterException extends RuntimeException implements ContainerExceptionInterface
{
    public function __construct($message = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message ?? 'Access to the service has not been granted.', $code, $previous);
    }
}
