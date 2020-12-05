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
    use WithStuff;

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

    public function testCreatedUsingMethodConstruct(): void
    {
        $ip = new Simpleton();
        $sheep = $ip->construct(Sheep::class);
        $this->assertInstanceOf(Sheep::class, $sheep);
    }

    public function testProvidesArgumentsForConstruction(): void
    {
        $ip = new Simpleton(new Constant(0.0), new Coefficient(1.0));
        $offset = $ip->construct(Offset::class);
        $this->assertInstanceOf(Offset::class, $offset);
    }

    public function testInvocationTypeSwitch()
    {
        $ip = new Simpleton(42.0);

        $constant = $ip(Constant::class);
        $this->assertInstanceOf(Constant::class, $constant);

        $hasBeenCalled = false;
        $coefficient = $ip(function (float $factor) use (&$hasBeenCalled) {
            $hasBeenCalled = true;
            return new Coefficient($factor);
        });
        $this->assertTrue($hasBeenCalled);
        $this->assertInstanceOf(Coefficient::class, $coefficient);

        $self = $this;
        $this->with($coefficient, function () use ($self) {
            $self->assertSame(42.0, $this->factor);
        });
        $this->with($constant, function () use ($self) {
            $self->assertSame(42.0, $this->value);
        });
    }
}
