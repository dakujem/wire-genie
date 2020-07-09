<?php
declare(strict_types=1);

namespace Dakujem\Tests;

use Dakujem\WireLimiter;
use Dakujem\WireLimiterException;
use Error;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

require_once 'ContainerProvider.php';

/**
 * @internal test
 */
final class WireLimiterTest extends TestCase
{
    public function testThrow(): void
    {
        $sleeve = ContainerProvider::createContainer();
        $wl = new WireLimiter($sleeve, [
            Error::class, // only allow to return Errors :-)
        ]);
        $this->expectException(ContainerExceptionInterface::class);
        $wl->get('foo');
    }

    public function testThrowWireLimiterException(): void
    {
        $sleeve = ContainerProvider::createContainer();
        $wl = new WireLimiter($sleeve, [
            Error::class, // only allow to return Errors :-)
        ]);
        $this->expectException(WireLimiterException::class);
        $wl->get('genie');
    }

    public function testReturnCorrectly(): void
    {
        $sleeve = ContainerProvider::createContainer();
        $wl = new WireLimiter($sleeve, [
            Error::class, // only allow to return Errors :-)
        ]);
        $this->assertTrue($wl->get(Error::class) instanceof Error);
    }

    public function testLimitdoesNotAffectHas(): void
    {
        $sleeve = ContainerProvider::createContainer();
        $wl = new WireLimiter($sleeve, [
            Error::class, // only allow to return Errors :-)
        ]);
        // limiting does not affect the has method
        $this->assertTrue($wl->has('genie'));
        $this->assertFalse($wl->has('foo'));
    }

    public function testLimiterWithEmptyWhitelistWillRefuseToReturnAnything(): void
    {
        $sleeve = ContainerProvider::createContainer();
        $wl = new WireLimiter($sleeve, []);
        $this->throws(WireLimiterException::class, function () use ($wl) {
            $wl->get('genie');
        });
        $this->throws(WireLimiterException::class, function () use ($wl) {
            $wl->get(Error::class);
        });
        $this->throws(ContainerExceptionInterface::class, function () use ($wl) {
            $wl->get('whatever');
        });
    }

    private function throws(string $exception, callable $func)
    {
        try {
            call_user_func($func);
        } catch (Throwable $e) {
        }
        $this->assertTrue(($e ?? null) instanceof $exception, 'Failed to throw ' . $exception . '. ' . (isset($e) ? 'Throwable ' . get_class($e) : 'Nothing') . ' was thrown.');
    }
}
