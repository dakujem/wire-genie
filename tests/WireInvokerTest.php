<?php

declare(strict_types=1);

namespace Dakujem\Tests;

use Dakujem\ArgInspector;
use Dakujem\WireInvoker;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionFunctionAbstract;

require_once 'AssertsErrors.php';

final class WireInvokerTest extends TestCase
{
    use AssertsErrors;

    public function testMixingArguments()
    {
        $provider = function ($id) {
            return $id; // normally this returns a service registered under $id service identifier
        };
        $identifiers = [
            'foo',
            null,
            'bar',
            null,
            'wham',
        ];

        $staticArguments = [1, 2, 3, 42, 'foobar'];
        $arguments = WireInvoker::resolveServicesFillingInStaticArguments($identifiers, $provider, $staticArguments);
        $this->assertSame([
            'foo',
            1,
            'bar',
            2,
            'wham',
            3,
            42,
            'foobar',
        ], $arguments);

        $staticArguments = ['foobar'];
        $arguments = WireInvoker::resolveServicesFillingInStaticArguments($identifiers, $provider, $staticArguments);
        $this->assertSame([
            'foo',
            'foobar',
            'bar',
            null,
            'wham',
        ], $arguments);
    }

    public function testInvoker()
    {
        $invoker = new WireInvoker(ContainerProvider::createContainer());

        $func = function (Foo $foo, int $theAnswer) {
            return [$foo, $theAnswer];
        };
        $invokable = new class {
            public function __invoke(Foo $foo, int $theAnswer)
            {
                return [$foo, $theAnswer];
            }
        };

        $check = function ($args) {
            $this->assertInstanceOf(Foo::class, $args[0]);
            $this->assertSame(42, $args[1]);
        };

        $rv = $invoker->invoke($func, 42);
        $check($rv);
        $rv = $invoker->invoke($invokable, 42);
        $check($rv);
        $rv = $invoker->invoke([$this, 'methodFoo'], 42);
        $check($rv);
        $rv = $invoker->invoke('\sleep', 0);
        $this->assertSame(0, $rv); // sleep returns 0 on success
        $rv = $invoker->invoke(self::class . '::methodBar', 42);
        $check($rv);

        $func2 = function () {
            return func_get_args();
        };
        $this->assertSame([], $invoker->invoke($func2));
        $this->assertSame([42], $invoker->invoke($func2, 42));
        $this->assertSame(['foo'], $invoker->invoke($func2, 'foo'));
    }

    public function testConstructor()
    {
        $invoker = new WireInvoker($sleeve = ContainerProvider::createContainer());
        $rv = $invoker->construct(WeepingWillow::class);
        $this->assertInstanceOf(WeepingWillow::class, $rv);
        $this->assertSame([], $rv->args);

        $rv = $invoker->construct(HollowWillow::class);
        $this->assertInstanceOf(HollowWillow::class, $rv);
        $this->assertSame([$sleeve->get(Foo::class)], $rv->args);
    }

    public function testInvalidInvocation1()
    {
        $invoker = new WireInvoker(ContainerProvider::createContainer());

        // passes ok
        $invoker->invoke([$this, 'methodFoo'], 42);

        $this->expectErrorMessage('Too few arguments to function Dakujem\Tests\WireInvokerTest::methodFoo(), 1 passed and exactly 2 expected');

        // type error, missing argument
        $invoker->invoke([$this, 'methodFoo']);
    }

    public function testInvalidInvocation2()
    {
        $invoker = new WireInvoker(ContainerProvider::createContainer());

        $func = function (Foo $foo, int $theAnswer) {
            return [$foo, $theAnswer];
        };
        $func2 = function (Foo $foo, int $theAnswer = null) {
            return [$foo, $theAnswer];
        };

        // passes ok
        $invoker->invoke($func, 42);
        $invoker->invoke($func2);

        $this->expectErrorMessage('Too few arguments to function Dakujem\Tests\WireInvokerTest::Dakujem\Tests\{closure}(), 1 passed and exactly 2 expected');

        // type error, missing argument
        $invoker->invoke($func);
    }

    public function testInvokerUsesCustomCallables()
    {
        $sleeve = ContainerProvider::createContainer();
        $detectorCalled = 0;
        $detector = function (ReflectionFunctionAbstract $ref) use (&$detectorCalled) {
            $detectorCalled += 1;
            return ArgInspector::detectTypes($ref);
        };
        $proxyCalled = 0;
        $proxy = function ($id, ContainerInterface $container) use (&$proxyCalled) {
            $proxyCalled += 1;
            return $container->get($id);
        };
        $reflectorCalled = 0;
        $reflector = function ($target) use (&$reflectorCalled) {
            $reflectorCalled += 1;
            return ArgInspector::reflectionOf($target);
        };
        $invoker = new WireInvoker($sleeve, $detector, $proxy, $reflector);
        [$bar, $fourtyTwo] = $invoker->invoke([$this, 'methodTagOverride'], 42);
        $this->assertSame(1, $reflectorCalled);
        $this->assertSame(1, $detectorCalled);
        $this->assertSame(1, $proxyCalled);
        $this->assertSame($sleeve->get(Bar::class), $bar);
        $this->assertSame(42, $fourtyTwo);
    }

    public function testInvokerUsesCustomCallablesWithTagReader()
    {
        $sleeve = ContainerProvider::createContainer();
        $detectorCalled = 0;
        $detector = function (ReflectionFunctionAbstract $ref) use (&$detectorCalled) {
            $detectorCalled += 1;
            return ArgInspector::detectTypes($ref, ArgInspector::tagReader()); // added tag reader
        };
        $proxyCalled = 0;
        $proxy = function ($id, ContainerInterface $container) use (&$proxyCalled) {
            $proxyCalled += 1;
            return $container->get($id);
        };
        $reflectorCalled = 0;
        $reflector = function ($target) use (&$reflectorCalled) {
            $reflectorCalled += 1;
            return ArgInspector::reflectionOf($target);
        };
        $invoker = new WireInvoker($sleeve, $detector, $proxy, $reflector);
        [$baz, $genie, $fourtyTwo, $foo] = $invoker->invoke([$this, 'methodTagOverride'], 42, 'foobar');
        $this->assertSame(1, $reflectorCalled);
        $this->assertSame(1, $detectorCalled);
        $this->assertSame(2, $proxyCalled);
        $this->assertSame($sleeve->get(Baz::class), $baz); // Baz, not Bar !
        $this->assertSame($sleeve->get('genie'), $genie);
        $this->assertSame(42, $fourtyTwo); // rest arguments trail
        $this->assertSame('foobar', $foo); // rest arguments trail
    }

    public function methodFoo(Foo $foo, int $theAnswer): array
    {
        return func_get_args();
    }

    public static function methodBar(Foo $foo, int $theAnswer): array
    {
        return func_get_args();
    }

    /**
     * @param Bar $bar [wire:Dakujem\Tests\Baz]
     * @param mixed $theAnswer [wire:genie]
     */
    public function methodTagOverride(Bar $bar, $theAnswer): array
    {
        return func_get_args();
    }
}
