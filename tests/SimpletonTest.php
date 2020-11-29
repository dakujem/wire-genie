<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Simpleton;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * @internal test
 */
final class SimpletonTest extends TestCase
{
    public function testCallable(): void
    {
        $ip = new Simpleton();

        $this->assertIsCallable($ip, 'Simpleton is not callable.');

        $hasBeenCalled = false;
        $ip(function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
        });

        $this->assertTrue($hasBeenCalled);

        $hasBeenCalled = false;
        $ip->invoke(function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
        });

        $this->assertTrue($hasBeenCalled);
    }

    public function testInvoked(): void
    {
        $ip = new Simpleton();

        $hasBeenCalled = false;
        $ip(function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
        });

        $this->assertTrue($hasBeenCalled);
    }

    public function testInvokedUsingMethodInvoke(): void
    {
        $ip = new Simpleton();

        $hasBeenCalled = false;
        $ip->invoke(function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
        });

        $this->assertTrue($hasBeenCalled);
    }

    public function testProvidesArguments(): void
    {
        $instance1 = new stdClass();
        $instance2 = new stdClass();
        $instance3 = new ReflectionClass(Simpleton::class);
        $ip = new Simpleton($instance1, $instance2, $instance3, 42);

        $this->assertIsCallable($ip, 'Simpleton is not callable.');

        $hasBeenInvokedTwice = 0;
        $ip->invoke(function ($arg1, $arg2, $arg3, $scalar) use (
            $instance1,
            $instance2,
            $instance3,
            &$hasBeenInvokedTwice
        ) {
            $this->assertSame($instance1, $arg1);
            $this->assertSame($instance2, $arg2);
            $this->assertSame($instance3, $arg3);
            $this->assertSame(42, $scalar);
            $hasBeenInvokedTwice += 1;
        });
        $ip(function ($arg1, $arg2, $arg3, $scalar) use (
            $instance1,
            $instance2,
            $instance3,
            &$hasBeenInvokedTwice
        ) {
            $this->assertSame($instance1, $arg1);
            $this->assertSame($instance2, $arg2);
            $this->assertSame($instance3, $arg3);
            $this->assertSame(42, $scalar);
            $hasBeenInvokedTwice += 1;
        });

        $this->assertSame(2, $hasBeenInvokedTwice); // ensure the callables were actually called
    }
}
