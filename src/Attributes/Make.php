<?php

declare(strict_types=1);

namespace Dakujem\Wire\Attributes;

use Attribute;

/**
 * Attempt to construct the given service.
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
#[Attribute]
final class Make implements ConstructionWireHint
{
    private array $args;

    public function __construct(private string $name, ...$args)
    {
        $this->args = $args;
    }

    public function getClassName(): string
    {
        return $this->name;
    }

    public function getConstructorArguments(): iterable
    {
        return $this->args;
    }
}
