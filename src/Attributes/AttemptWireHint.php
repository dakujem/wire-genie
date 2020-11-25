<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

/**
 * A hint for the wiring mechanism to attempt to construct an instance of the type-hinted service(s)
 * if the service container can not provide one.
 * The target service is given by the type-hint of the parameter.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
interface AttemptWireHint extends Attribute
{
}
