<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

use Attribute;

/**
 * Do not wire the parameter, fill it in from the static arguments, if possible.
 * @see SuppressionWireHint
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
#[Attribute]
class Skip implements SuppressionWireHint
{

}
