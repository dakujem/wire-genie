<?php

namespace Dakujem\Tests;

use Dakujem\Provider;
use Dakujem\Invoker;
use Dakujem\WireGenie;
use Dakujem\WireLimiter;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @internal test
 */
final class WireGenieTest extends TestCase
{
    public function testProvideReturnsAninvokableProvider()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $this->assertTrue($wg->provide() instanceof Provider);
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

    public function testContainerExposing()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new WireGenie($sleeve);

        $container = $wg->exposeContainer(function (ContainerInterface $container) {
            return $container;
        });
        $this->assertSame($sleeve, $container);

        // test that the call actually returns the value returned by the callable
        $rv = $wg->exposeContainer(function () {
            return 42;
        });
        $this->assertSame(42, $rv);
    }

    private function check(array $expected, Invoker $p)
    {
        $this->assertSame($expected, $p->invoke(function (...$args) {
            return $args;
        }));
    }
}
