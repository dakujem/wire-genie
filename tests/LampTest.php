<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\Genie;
use Dakujem\Wire\Lamp;
use PHPUnit\Framework\TestCase;

/**
 * @internal test
 */
final class LampTest extends TestCase
{
    use WithStuff;

    public function testRubbing(): void
    {
        $lamp = new Lamp($c = new Sleeve(), $core = fn() => null);

        $g = $lamp->rub();

        $self = $this;
        $this->assertInstanceOf(Genie::class, $g);
        $this->with($g, function () use ($self, $core, $c) {
            $self->assertSame($core, $this->core);
            $self->assertSame($c, $this->container);
        });
    }
}
