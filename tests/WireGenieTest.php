<?php

namespace Dakujem\Tests;

use Dakujem\InvokableProvider;
use Dakujem\Invoker;
use Dakujem\WireGenie;
use Dakujem\WireLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * WireGenieTest
 */
final class WireGenieTest extends TestCase
{
    public function testProvideReturnsAninvokableProvider()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $this->assertTrue($wg->provide() instanceof InvokableProvider);
    }

    public function testProvisioning()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $p = $wg->provide('ref1', 'genie', WireGenie::class);
        $this->check([
            $sleeve->get('ref1'),
            $sleeve->get('genie'),
            $sleeve->get(WireGenie::class),
        ], $p);
    }

    public function testStrictProvisioning()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $p = $wg->provideStrict('ref1', 'genie', WireGenie::class);
        $this->check([
            $sleeve->get('ref1'),
            $sleeve->get('genie'),
            $sleeve->get(WireGenie::class),
        ], $p);
    }

    public function testSafeProvisioning()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $p = $wg->provideSafe('ref1', 'genie', WireGenie::class);
        $this->check([
            $sleeve->get('ref1'),
            $sleeve->get('genie'),
            $sleeve->get(WireGenie::class),
        ], $p);
    }

    public function testNullResolution()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $p = $wg->provide('unknown', 'genie');
        $this->check([
            null,
            $sleeve->get('genie'),
        ], $p);
    }

    public function testStrictThrow()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $this->expectException(NotFoundExceptionInterface::class);
        $wg->provideStrict('unknown', 'genie');  // this would NOT have thrown if using `provide`
    }

    public function testSafeNullResolution()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie(new WireLimiter($sleeve, []));

        $p = $wg->provideSafe('unknown', 'genie'); // this would have thrown if using either `provide` or `provideStrict`
        $this->check([null, null,], $p);
    }

    public function testThrow()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie(new WireLimiter($sleeve, [])); // will throw when accessing any service

        $this->expectException(ContainerExceptionInterface::class);
        $wg->provide('self');
    }

    public function testCustomResolver()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $p = $wg->wire(function () {
            return [];
        });
        $this->check([], $p);
        $p = $wg->wire(function () {
            return [1, 2, 3, 42, 'foo'];
        });
        $this->check([1, 2, 3, 42, 'foo'], $p);
    }

    public function testCustomResolverIsPassedCorrectArguments()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $p = $wg->wire(function ($deps, $container) use ($sleeve) {
            $this->assertSame([1, 2, 3, 42, 'foo'], $deps);
            $this->assertSame($sleeve, $container);
            return [];
        }, 1, 2, 3, 42, 'foo');
        $this->check([], $p);

        $checkFunc = function () {
        };
        $wg->wire(function ($dependencies, $container, $target) use ($sleeve, $checkFunc) {
            $this->assertSame([], $dependencies); //   the rest arguments to the `wire` call are treated as dependencies
            $this->assertSame($sleeve, $container); // second argument is the container of wire genie
            $this->assertSame($checkFunc, $target); // the last argument to the resolver is the target function
            return [];
        })->invoke($checkFunc);
    }

    private function check(array $expected, Invoker $p)
    {
        $this->assertSame($expected, $p->invoke(function (...$args) {
            return $args;
        }));
    }
}
