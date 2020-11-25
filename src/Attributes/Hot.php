<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

use Attribute;

/**
 * Attempt to create the type-hinted services.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
#[Attribute]
final class Hot implements AttemptWireHint
{
}
