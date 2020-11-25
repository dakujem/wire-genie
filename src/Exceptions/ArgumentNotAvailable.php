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
}
