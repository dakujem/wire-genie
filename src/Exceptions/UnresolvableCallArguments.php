<?php

declare(strict_types=1);

namespace Dakujem\Wire\Exceptions;

use RuntimeException;

/**
 * UnresolvableCallArguments
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
class UnresolvableCallArguments extends RuntimeException implements Unresolvable
{
}
