<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\Attributes\Hot;
use Dakujem\Wire\Attributes\Make;
use Dakujem\Wire\Attributes\Skip;
use Dakujem\Wire\Attributes\Wire;
use Dakujem\Wire\Genie;
use Dakujem\Wire\Lamp;
use Psr\Container\ContainerInterface;

require_once 'testHelperClasses.php';

/**
 * This test kinda demonstrates what is possible.
 *
 * @internal test
 */
final class GenieAttributesShowcaseTest extends GenieBaseTest
{
    public function testEverything()
    {
        $sleeve = new Sleeve([
            ContainerInterface::class => fn($c) => $c,
            'foobar' => 'this is a faux value',
            MyService::class => fn($c) => new MyService($c[Thing::class], $c['foobar']),
            Thing::class => fn() => new Thing(),
        ]);
        $toCall = function (
            ?Thing $s1,
            #[Make(MyService::class, 42), Wire(MyService::class)] ServiceInterface $s2, // wire MyService from container
            #[Make(Lamp::class)] Lamp $lamp, // construct a Lamp from services in container
            $notAutowired, // 'not autowired'
            $value, // 42
            #[Wire('foobar')] $foo,
            #[Hot] MyOtherService $s3, // not in the container, construct it
            #[Skip] ?Thing $skap, // do not autowire this parameter using the service container
            Thing|MyService $union, // + union types
            Lamp|MyService $notALamp,
            $another, // 'another value'
            ...$andTheRest // [ 'rem...', 123 ]
        ): array {
            return func_get_args();
        };

        $args = (new Genie($sleeve))->provision(
            $toCall,
            'not autowired',
            null,
            'another value',
            'these are the remaining arguments',
            123,
            value: 42,
        );

        $this->assertInstanceOf(Thing::class, $args[0] ?? null);
        $this->assertSame($sleeve[Thing::class], $args[0] ?? null);

        $this->assertInstanceOf(MyService::class, $args[1] ?? null);
        $this->assertSame($sleeve[MyService::class], $args[1] ?? null);

        $this->assertInstanceOf(Lamp::class, $args[2] ?? null);

        $this->assertSame('not autowired', $args[3] ?? null);

        $this->assertSame(42, $args[4] ?? null);

        $this->assertSame($sleeve['foobar'], $args[5] ?? null);

        $this->assertInstanceOf(MyOtherService::class, $args[6] ?? null);

        $this->assertSame(null, $args[7] ?? null);

        $this->assertInstanceOf(Thing::class, $args[8] ?? null);
        $this->assertSame($sleeve[Thing::class], $args[8] ?? null);

        $this->assertInstanceOf(MyService::class, $args[9] ?? null);
        $this->assertSame($sleeve[MyService::class], $args[9] ?? null);

        $this->assertSame('another value', $args[10] ?? null);

        $this->assertSame('these are the remaining arguments', $args[11] ?? null);
        $this->assertSame(123, $args[12] ?? null);

        $this->assertCount(13, $args);
    }
}
