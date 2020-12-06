<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\Genie;
use PHPUnit\Framework\TestCase;

/**
 * @internal test
 */
class GenieCoreTest extends TestCase
{
    use WithStuff;

    public function testGenieUsesACoreProperlyPassingArguments()
    {
        $passed = [];
        $core = function (Genie $g, $t, ...$args) use (&$passed): iterable {
            $c = $g->exposeContainer(function ($c) {
                return $c;
            });
            $passed = [$g, $t, $args, $c];
            return [];
        };

        $sleeve = ContainerProvider::createContainer();
        $genie = new Genie($sleeve, $core);
        $target = function () {
            return 'ok';
        };

        $ok = $genie->invoke($target, 'foobar');

        $this->assertSame('ok', $ok);
        $this->assertSame($genie, $passed[0]);
        $this->assertSame($target, $passed[1]);
        $this->assertSame(['foobar'], $passed[2]);
        $this->assertSame($sleeve, $passed[3]);
    }

    public function testEmploy()
    {
        $c = new Sleeve();
        $core = function () {
            return null;
        };

        $self = $this;
        $contains = function () use ($self, $core, $c) {
            /* $this will be bound to the Genie instance */
            $self->assertSame($core, $this->core);
            $self->assertSame($c, $this->container);
        };
        $this->with(new Genie($c, $core), $contains);
        $this->with(Genie::employ($c, $core), $contains);
    }
}
