<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

use Attribute;

/**
 * Attempt to create the type-hinted services.
 *
 * Note:
 *   It is the intention not to provide constructor arguments since for union types the arguments might not match.
 *   Use Make attribute in cases where you need to provide constructor arguments.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
#[Attribute]
final class Hot implements AttemptWireHint
{
}
