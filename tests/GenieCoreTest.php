<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\Exceptions\Unresolvable;
use Dakujem\Wire\Exceptions\UnresolvableCallArguments;
use Dakujem\Wire\Genie;
use PHPUnit\Framework\TestCase;

/**
 * @internal test
 */
class GenieCoreTest extends TestCase
{
    use WithStuff;
    use AssertsErrors;

    public function testGenieProvisionsCallArguments()
    {
        $g = new Genie($c = new Sleeve([
            Frog::class => new Frog(),
        ]));
        $f1 = function (Frog $frog, int $answer) {
        };
        $args = $g->provision($f1, 42);
        $this->assertSame($c[Frog::class], $args[0]);
        $this->assertSame(42, $args[1]);
        $this->assertCount(2, $args);
    }

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

    public function testInvocationTypeSwitch()
    {
        $g = new Genie($c = new Sleeve([
            Constant::class => new Constant(0.0),
            Coefficient::class => new Coefficient(1.0),
            Offset::class => function ($c) {
                return new Offset($c[Constant::class], $c[Coefficient::class]);
            },
        ]));

        $o1 = $g(Offset::class);
        $o2 = $g(function (Offset $offset) {
            return $offset;
        });

        $this->assertSame($c[Offset::class], $o2);
        $this->assertInstanceOf(Offset::class, $o1);
        $this->assertNotSame($c[Offset::class], $o1); // must not be the same, one is created, other one is from the container
        $self = $this;
        $this->with($o1, function () use ($self, $c) {
            // assert that the services contained inside the newly created Offset instance come from the container
            $self->assertSame($c[Constant::class], $this->absolute);
            $self->assertSame($c[Coefficient::class], $this->relative);
        });
    }

    public function testUnresolvable()
    {
        $g = new Genie(new Sleeve([]), function () {
            throw new UnresolvableCallArguments('foo');
        });

        $this->assertException(function () use ($g) {
            $g(function () {
            });
        }, Unresolvable::class);
    }
}
