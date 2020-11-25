<?php

declare(strict_types=1);

namespace Dakujem\Wire\Exceptions;

use Dakujem\WireLimiterException;
use Psr\Container\ContainerExceptionInterface;
use RuntimeException;
use Throwable;

/**
 * The access the service is not allowed.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class ServiceNotWhitelisted extends RuntimeException implements ContainerExceptionInterface, WireLimiterException
{
    public function __construct($message = null, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message ?? 'Access to the service has not been granted.', $code, $previous);
    }
}
