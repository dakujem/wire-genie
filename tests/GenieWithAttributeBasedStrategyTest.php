<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\Attributes\Hot;
use Dakujem\Wire\Attributes\Make;
use Dakujem\Wire\Attributes\Skip;
use Dakujem\Wire\Attributes\Wire;
use Dakujem\Wire\Exceptions\Unresolvable;
use Dakujem\Wire\Exceptions\UnresolvableCallArguments;
use Dakujem\Wire\Genie;
use Error;
use Psr\Container\ContainerInterface as C;

require_once 'AssertsErrors.php';
require_once 'testHelperClasses.php';

/**
 * @internal test
 */
final class GenieWithAttributeBasedStrategyTest extends GenieBaseTest
{
    public function testResolveByTypeHint()
    {
        $f = fn(Thing $some) => func_get_args();
        $g = new Genie($c = new Sleeve([
            Thing::class => fn() => new Thing(),
        ]));
        $args = $g->invoke($f);
        $this->assertSame($c[Thing::class], $args[0]);
        $this->assertCount(1, $args);
    }

    public function testUtterFailure()
    {
        $f = fn(int $i) => func_get_args();
        $g = new Genie(new Sleeve([]));
        $this->assertException(fn() => $g->invoke($f), Unresolvable::class);
    }

    public function testNullable()
    {
        $f = fn(?int $i) => func_get_args();
        $g = new Genie(new Sleeve([]));
        $args = $g->invoke($f);
        $this->assertSame([null], $args);
    }

    public function testDefaultValue()
    {
        $f = fn(int $i = 42) => func_get_args();
        $g = new Genie(new Sleeve([]));
        $args = $g->invoke($f);
        $this->assertSame([42], $args);
    }

    public function testPool()
    {
        $f = fn(string $foo, int $i = 42) => func_get_args();
        $g = new Genie(new Sleeve([]));
        $this->assertException(fn() => $g->invoke($f), Unresolvable::class);
        $args = $g->invoke($f, 'foobar');
        $this->assertSame(['foobar', 42], $args);
    }

    public function testPoolReminder()
    {
        $f = fn() => func_get_args();
        $g = new Genie(new Sleeve([]));
        $this->assertSame([], $g->invoke($f));
        $this->assertSame([42], $g->invoke($f, 42));
        $this->assertSame([42, 'foo'], $g->invoke($f, 42, 'foo'));
        $this->assertSame([42, $g], $g->invoke($f, 42, $g));

        $f2 = fn($a) => func_get_args();
        $this->assertSame(['A'], $g->invoke($f2, 'A'));
        $this->assertSame(['A', 'b'], $g->invoke($f2, 'A', 'b'));
    }

    public function testVariadic()
    {
        $f = fn(...$args) => $args;
        $g = new Genie(new Sleeve([]));
        $this->assertSame([], $g->invoke($f));
        $this->assertSame([42, 'foo'], $g->invoke($f, 42, 'foo'));

        $f2 = fn($a, ...$args) => $args;
        $this->assertSame([], $g->invoke($f2, 'A'));
        $this->assertSame(['b'], $g->invoke($f2, 'A', 'b'));
    }

    public function testTypeHintPriority()
    {
        $f = fn(Thing $some) => func_get_args();
        $g = new Genie($c = new Sleeve([
            Thing::class => fn() => new Thing(),
        ]));
        $args = $g->invoke($f, 'foobar');
        $this->assertSame($c[Thing::class], $args[0]);
    }

    public function testMixedPoolProvisioning()
    {
        $f = fn(string $foo, Thing $some, int $i) => func_get_args();
        $g = new Genie($c = new Sleeve([
            Thing::class => fn() => new Thing(),
        ]));
        $this->assertException(fn() => $g->invoke($f), Unresolvable::class);
        $args = $g->invoke($f, 'foobar', 42);
        $this->assertSame(['foobar', $c[Thing::class], 42], $args);
    }

    public function testVariadicWithNamedArguments()
    {
        $f1 = fn($a, ...$args) => $args;
        $g = new Genie(new Sleeve([]));
        $this->assertSame(['b'], $g->invoke($f1, 'b', a: 'a'));
        $this->assertSame(['args' => 'a'], $g->invoke($f1, 'b', args: 'a'));
        $this->assertSame(['c', 'args' => 'b'], $g->invoke($f1, 'c', args: 'b', a: 'a'), Error::class);
        $this->assertSame(['b', 'c', 'foo' => 'bar'], $g->invoke($f1, 'b', 'c', a: 'a', foo: 'bar'));

        $f2 = fn($a) => func_get_args(); // no variadic param
        $this->assertSame(['A'], $g->invoke($f2, a: 'A'));
        $this->assertException(fn(
        ) => $g->invoke($f2, a: 'A', b: 'B', foo: 'bar'), Error::class); // not allowed this time
    }

    /**
     * #[Skip] marks the parameter unresolvable, so the arguments must be filled in from the pool, defaults or nulls for nullable ones.
     */
    public function testSkipHintPriority()
    {
        $f1 = fn(
            Thing $r,
            #[Skip] Thing $a,
            #[Skip, Make(Thing::class)] Thing $b,
            #[Skip, Hot] Thing $c,
            #[Skip, Wire(C::class)] C $skipped,
            #[Skip] ?Thing $d,
            ?C $resolved = null
        ) => func_get_args();
        $g = new Genie($container = new Sleeve([
            C::class => fn($c) => $c,
            Thing::class => fn() => new Thing(),
        ]));
        $a = new Thing;
        $b = new Thing;
        $c = new Thing;
        $skipped = new Sleeve();
        $this->assertSame([
            $container[Thing::class], // $r
            $a,
            $b,
            $c,
            $skipped,
            null,
            $container, // $resolved
        ], $g->invoke($f1, $a, $b, $c, $skipped));
    }

    public function testWireHintPriority()
    {
        $f0 = fn(Animal $sheep) => func_get_args();
        $f1 = fn(
            #[Wire(Sheep::class)] Animal $sheep,
            #[Wire(Sheep::class), Make(Wolf::class)] Animal $wolf, // should be Sheep
            #[Wire(Sheep::class), Make(Frog::class)] Animal $frog, // should be Sheep
            #[Wire(Sheep::class), Hot] Animal $hotSheep,
            Sheep $realSheep,
            Wolf $realWolf
        ) => func_get_args();
        $g = new Genie($c = new Sleeve([
            Sheep::class => fn() => new Sheep(),
            Wolf::class => fn() => new Wolf(),
        ]));

        $this->assertException(fn() => $g->invoke($f0), Unresolvable::class, 'Unresolvable: \'sheep\'.');
        $this->assertSame([
            $c[Sheep::class],
            $c[Sheep::class],
            $c[Sheep::class],
            $c[Sheep::class],
            $c[Sheep::class],
            $c[Wolf::class],
        ], $g($f1));
    }

    public function testHotHintPriority()
    {
        $f0 = fn(Frog $frog) => func_get_args();
        $f1 = fn(
            #[Hot] Sheep $service, // should be resolved from container
            #[Hot] Frog $frog,
            #[Hot, Make(Wolf::class)] Frog $wolf,
            #[Hot, Wire(Sheep::class)] Animal $sheep, // a sheep will be wired
        ) => func_get_args();
        $g = new Genie($c = new Sleeve([
            Sheep::class => fn() => new Sheep(),
        ]));

        $this->assertException(fn() => $g->invoke($f0), Unresolvable::class, 'Unresolvable: \'frog\'.');

        $args = $g($f1);
        $this->assertSame($c[Sheep::class], $args[0] ?? null);
        $this->assertInstanceOf(Frog::class, $args[1] ?? null);
        $this->assertInstanceOf(Frog::class, $args[2] ?? null);
        $this->assertSame($c[Sheep::class], $args[3] ?? null);
        $this->assertNotSame($args[1] ?? null, $args[2] ?? null); // separate instances each
    }

    public function testMakeHintPriority()
    {
        $f0 = fn(Animal $a) => func_get_args();
        $f1 = fn(
            #[Make(Wolf::class)] Animal $wolf,
            #[Hot, Make(Sheep::class)] Animal $sheep, // Hot takes precedence, Animal should be here
            #[Wire(Lion::class), Make(Frog::class)] Animal $lion, // Wire takes precedence, but fails
            #[Wire(Elephant::class), Make(Frog::class)] Animal $fant, // Wire takes precedence
            #[Make(Frog::class)] Elephant $elephant, // type hint has precedence
        ) => func_get_args();
        $g = new Genie($c = new Sleeve([
            Elephant::class => fn() => new Elephant(),
        ]));

        $this->assertException(fn() => $g($f0), Unresolvable::class, 'Unresolvable: \'a\'.');

        $args = $g($f1);
        $this->assertInstanceOf(Wolf::class, $args[0] ?? null);
        $this->assertSame(Animal::class, get_class($args[1] ?? null));
        $this->assertInstanceOf(Frog::class, $args[2] ?? null);
        $this->assertSame($c[Elephant::class], $args[3] ?? null);
        $this->assertSame($c[Elephant::class], $args[4] ?? null);

        $this->assertCount(5, $args);
    }

    public function testNamedArgumentPriority()
    {
        $g = new Genie($c = new Sleeve([
            Sheep::class => fn() => new Sheep(),
            Wolf::class => fn() => new Wolf(),
            Elephant::class => fn() => new Elephant(),
        ]));

        $f = fn(
            Sheep $sheep,
            #[Wire(Wolf::class)] Animal $wolf,
            #[Make(Lion::class)] Animal $lion,
            #[Hot] ?Frog $frog,
            #[Skip] ?Elephant $elephant = null
        ) => func_get_args();

        // without named args, the services are resolved or constructed as expected
        $auto = $g($f);
        $this->assertSame($c[Sheep::class], $auto[0] ?? null);
        $this->assertSame($c[Wolf::class], $auto[1] ?? null);
        $this->assertInstanceOf(Lion::class, $auto[2] ?? null);
        $this->assertInstanceOf(Frog::class, $auto[3] ?? null);
        $this->assertArrayHasKey(4, $auto);
        $this->assertNull($auto[4]);
        $this->assertCount(5, $auto);

        // passing named arguments to the pool will override any automatic wiring
        $named = $g(
            $f,
            lion: new Frog,
            wolf: new Sheep,
            sheep: new Sheep,
            frog: null,
            elephant: new Elephant,
        );
        $this->assertInstanceOf(Sheep::class, $named[0] ?? null);
        $this->assertNotSame($c[Sheep::class], $named[0] ?? null); // must not be the same as the one in the container !
        $this->assertInstanceOf(Sheep::class, $named[1] ?? null); // sheep instead of a wolf
        $this->assertInstanceOf(Frog::class, $named[2] ?? null); // frog instead of a lion
        $this->assertArrayHasKey(3, $auto);
        $this->assertNull($named[3]);
        $this->assertInstanceOf(Elephant::class, $named[4] ?? null);
        $this->assertCount(5, $named);
    }

    public function testInvokerFillsInArguments()
    {
        $invoker = new Genie(ContainerProvider::createContainer());
        $this->_FillsInArguments($invoker);
    }

    public function testInvokerInvokesAnyCallableTypeAndFillsInUnresolvedArguments()
    {
        $invoker = new Genie(ContainerProvider::createContainer());
        $this->_InvokesAnyCallableTypeAndFillsInUnresolvedArguments($invoker);
    }

    public function testInvokerReadsAttributesByDefault()
    {
        $g = new Genie(ContainerProvider::createContainer());
        // tags should be read by default
        $rv = $g->invoke([$this, 'methodAttributeOverride'], 42);

        $this->assertCount(3, $rv);
        $this->assertInstanceOf(Sheep::class, $rv[0]);
        $this->assertInstanceOf(Genie::class, $rv[1]);
        $this->assertSame(42, $rv[2]);
    }

    public function testInvalidInvocation()
    {
        $g = new Genie(ContainerProvider::createContainer());

        // passes ok
        $g->invoke([$this, 'methodFoo'], 42);

        // unresolvable argument
        $this->assertException(
            function () use ($g) {
                $g->invoke([$this, 'methodFoo']);
            },
            UnresolvableCallArguments::class,
            'Unresolvable: \'theAnswer\'.'
        );

        $func = function (Plant $foo, int $theAnswer) {
            return [$foo, $theAnswer];
        };
        $func2 = function (Plant $foo, int $theAnswer = null) {
            return [$foo, $theAnswer];
        };

        // passes ok
        $g->invoke($func, 42);
        $g->invoke($func2);

        // unresolvable argument
        $this->assertException(
            function () use ($g, $func) {
                $g->invoke($func);
            },
            UnresolvableCallArguments::class,
            'Unresolvable: \'theAnswer\'.'
        );
    }

    public function testAutomaticResolutionCanBeSkipped()
    {
        $g = new Genie($sleeve = ContainerProvider::createContainer());
        $func = function (Animal $bar) {
            return func_get_args();
        };
        // normally resolves to Bar instance
        $this->assertSame([$sleeve->get(Animal::class)], $g->invoke($func));

        $funcSkipped = function (#[Skip] Animal $bar) {
            return func_get_args();
        };
        $sheep = $sleeve->get(Sheep::class); // Sheep, not Animal !
        // but here we turn the detection off and provide our own instance (of Baz)
        $this->assertSame([$sheep], $g->invoke($funcSkipped, $sheep));
    }

    public function testConstruction()
    {
        $g = new Genie($sleeve = ContainerProvider::createContainer());
        $rv = $g->construct(WeepingWillow::class);
        $this->assertInstanceOf(WeepingWillow::class, $rv);
        $this->assertSame([], $rv->args);

        $rv = $g->construct(HollowWillow::class);
        $this->assertInstanceOf(HollowWillow::class, $rv);
        $this->assertSame([$sleeve->get(Plant::class)], $rv->args);
    }
}
