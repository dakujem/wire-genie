<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Invoker;
use Dakujem\Wire\Genie;
use Dakujem\Wire\Simpleton;
use Dakujem\WireLimiter;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * @internal test
 */
final class GenieProvisioningTest extends TestCase
{
    public function testProvideReturnsASimpleton()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new Genie($sleeve);

        $this->assertTrue($wg->provide() instanceof Simpleton);
    }

    public function testGenieProvidesServices()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new Genie($sleeve);

        $p = $wg->provide('ref1', 'genie', Genie::class);
        $this->check([
            $sleeve->get('ref1'),
            $sleeve->get('genie'),
            $sleeve->get(Genie::class),
        ], $p);
    }

    public function testNullResolution()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new Genie($sleeve);

        $p = $wg->provide('unknown', 'genie');
        $this->check([
            null,
            $sleeve->get('genie'),
        ], $p);
    }

    public function testThrow()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new Genie(new WireLimiter($sleeve, [])); // will throw when accessing any service

        $this->expectException(ContainerExceptionInterface::class);
        $wg->provide('self');
    }

    public function testContainerExposing()
    {
        $sleeve = ContainerProvider::createContainer();
        $wg = new Genie($sleeve);

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

    public function testStrictProvisioning()
    {
        // method removed
        $this->expectException(LogicException::class);
        $sleeve = ContainerProvider::createContainer();
        (new Genie($sleeve))->provideStrict('whatever', 'genie', Genie::class);
    }

    public function testSafeProvisioning()
    {
        // method removed
        $this->expectException(LogicException::class);
        $sleeve = ContainerProvider::createContainer();
        (new Genie($sleeve))->provideSafe('whatever', 'genie', Genie::class);
    }
}
