<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

/**
 * A hint for the wiring mechanism to construct an instance of a service.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
interface ConstructionWireHint extends Attribute
{
    public function getClassName(): string;

    public function getConstructorArguments(): iterable;
}
