<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

/**
 * A hint for the wiring mechanism to skip the wiring for the parameter.
 * The parameter will be treated as if no type-hint was present and will be filled in from the static arguments.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
interface SuppressionWireHint extends Attribute
{
}
