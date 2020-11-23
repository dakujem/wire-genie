<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

use Attribute;

/**
 * Hot wire. Attempt to construct the service if not provided by the service container.
 * @see AttemptConstructionWireHint
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
#[Attribute]
class Hot implements AttemptConstructionWireHint
{
    private array $args;

    public function __construct(...$args)
    {
        $this->args = $args;
    }

    public function getConstructorArguments(): iterable
    {
        return $this->args;
    }
}
