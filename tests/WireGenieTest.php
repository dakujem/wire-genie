<?php

namespace Dakujem\Tests;

use Dakujem\InvokableProvider;
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
        $p = $wg->provideStrict('unknown', 'genie');  // this would NOT have thrown if using `provide`
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
        $p = $wg->provide('self');
    }

    private function check(array $expected, InvokableProvider $p)
    {
        $provided = null;
        call_user_func($p, function (...$args) use (&$provided) {
            $provided = $args;
        });
        $this->assertSame($expected, $provided);
    }
}
