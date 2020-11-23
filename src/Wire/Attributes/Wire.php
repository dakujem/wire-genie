<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

use Attribute;

/**
 * Wire hint.
 * A service will be fetched from the service container by the identifier.
 * @see IdentifierWireHint
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
#[Attribute]
class Wire implements IdentifierWireHint
{
    public function __construct(private string $identifier)
    {
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
