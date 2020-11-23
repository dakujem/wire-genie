<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

/**
 * A hint for the wiring mechanism to attempt to construct an instance of the target service
 * if the service container can not provide it.
 *
 * The target service is either given by the type-hint or by the identifier wiring hint attribute.
 * @see IdentifierWireHint
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
interface AttemptConstructionWireHint extends Attribute
{
    public function getConstructorArguments(): iterable;
}
