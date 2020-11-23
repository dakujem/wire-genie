<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

/**
 * IdentifierWireHint
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
interface IdentifierWireHint extends Attribute
{
    public function getIdentifier(): string;
}
