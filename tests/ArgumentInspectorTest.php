<?php

declare(strict_types=1);

namespace Dakujem\Tests;

use Dakujem\ArgInspector;
use Dakujem\WireGenie;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionFunctionAbstract;

require_once 'AssertsErrors.php';

final class ArgumentInspectorTest extends TestCase
{
    use AssertsErrors;

    public function testDetectTypes(): void
    {
        $closure = function (Foo $foo, Bar $bar) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = ArgInspector::detectTypes($reflection);
        $this->assertSame([Foo::class, Bar::class], $types);
    }

    public function testDetectTypesWithAScalar(): void
    {
        $closure = function (Foo $foo, int $qux, Bar $bar, $wham) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = ArgInspector::detectTypes($reflection);
        $this->assertSame([Foo::class, null, Bar::class, null], $types);
    }

    public function testDetectTypesWithoutParameters(): void
    {
        $closure = function () {
        };
        $reflection = new ReflectionFunction($closure);
        $this->assertSame([], ArgInspector::detectTypes($reflection));
    }

    public function testDetectTypesUsingTagReader(): void
    {
        $closure = function (Foo $foo, int $qux, Bar $bar, $wham) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = ArgInspector::detectTypes($reflection, ArgInspector::tagReader());
        $this->assertSame([Foo::class, null, Bar::class, null], $types);
    }

    public function testDetectTypesUsingTagReaderWithTags(): void
    {
        /**
         * @param Foo $foo [wire]
         * @param int $qux [notreally:nothing-sorry]
         * @param Bar $bar [wire:overridden]
         * @param $wham [wire:My\Name\Space\Wham]
         * @param $ham [wire:redundant]
         */
        $closure = function (Foo $foo, int $qux, Bar $bar, $wham) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = ArgInspector::detectTypes($reflection, ArgInspector::tagReader());
        $this->assertSame([Foo::class, null, 'overridden', \My\Name\Space\Wham::class], $types);
    }

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
        $arguments = ArgInspector::resolveServicesFillingInStaticArguments($identifiers, $provider, $staticArguments);
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
        $arguments = ArgInspector::resolveServicesFillingInStaticArguments($identifiers, $provider, $staticArguments);
        $this->assertSame([
            'foo',
            'foobar',
            'bar',
            null,
            'wham',
        ], $arguments);
    }

    public function testReflectionOf()
    {
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf(function () {
        }));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf(new class {
            public function __invoke()
            {
            }
        }));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf('\fopen'));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf([$this, 'methodFoo']));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf(self::class . '::methodBar'));

        // TODO construction of objects ??
//        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf([Bar::class, 'parent::__construct']));
    }

    public function testResolver()
    {
        $resolver = ArgInspector::resolver();

        $func = function (Foo $foo, int $theAnswer) {
        };
        $invokable = new class {
            public function __invoke(Foo $foo, int $theAnswer)
            {
            }
        };

        $check = function ($args) {
            $this->assertInstanceOf(Foo::class, $args[0]);
            $this->assertSame(null, $args[1]);
        };

        $rv = call_user_func($resolver, [], ContainerProvider::createContainer(), $func);
        $check($rv);
        $rv = call_user_func($resolver, [], ContainerProvider::createContainer(), $invokable);
        $check($rv);
        $rv = call_user_func($resolver, [], ContainerProvider::createContainer(), [$this, 'methodFoo']);
        $check($rv);
        $rv = call_user_func($resolver, [], ContainerProvider::createContainer(), '\sleep');
        $this->assertSame([null], $rv);
        $rv = call_user_func($resolver, [], ContainerProvider::createContainer(), self::class . '::methodBar');
        $check($rv);

        $check = function ($args) {
            $this->assertInstanceOf(Foo::class, $args[0]);
            $this->assertSame(42, $args[1]);
        };

        $rv = call_user_func($resolver, [42], ContainerProvider::createContainer(), $func);
        $check($rv);
        $rv = call_user_func($resolver, [42], ContainerProvider::createContainer(), $invokable);
        $check($rv);
        $rv = call_user_func($resolver, [42], ContainerProvider::createContainer(), [$this, 'methodFoo']);
        $check($rv);
        $rv = call_user_func($resolver, [42], ContainerProvider::createContainer(), '\sleep');
        $this->assertSame([42], $rv);
        $rv = call_user_func($resolver, [42], ContainerProvider::createContainer(), self::class . '::methodBar');
        $check($rv);
    }

    public function testResolverUsesDetectorAndFetcher()
    {
        $sleeve = ContainerProvider::createContainer();
        $called = 0;
        $fetcher = function ($id) use ($sleeve, &$called) {
            $called += 1;
            return $sleeve->get($id);
        };
        $resolver = ArgInspector::resolver(ArgInspector::tagReader(), $fetcher);
        [$foo, $fourtyTwo, $rest] = call_user_func($resolver, [42], $sleeve, [$this, 'methodFoo']);
        $this->assertSame(2, $called);
        $this->assertSame($sleeve->get('genie'), $foo);
        $this->assertSame($sleeve->get(Bar::class), $fourtyTwo);
        $this->assertSame(42, $rest);
    }

    public function testReusingResolverWithTags(): void
    {
        $closure = function (Foo $foo, int $qux, Bar $bar, $wham) {
        };
        $resolver = ArgInspector::resolver(ArgInspector::tagReader());

        $rv = call_user_func($resolver, [], ContainerProvider::createContainer(), $closure);
        $this->assertCount(4, $rv);
        $this->assertInstanceOf(Foo::class, $rv[0]);
        $this->assertInstanceOf(Bar::class, $rv[2]);
        $this->assertSame(null, $rv[1]);
        $this->assertSame(null, $rv[3]);

        // test to resolve arguments for a method with different signature
        $test = function ($resolver) {
            $rv = call_user_func($resolver, [], ContainerProvider::createContainer(), [$this, 'methodFoo']);
            $this->assertCount(2, $rv);
            $this->assertInstanceOf(WireGenie::class, $rv[0]);
            $this->assertInstanceOf(Bar::class, $rv[1]);
        };

        // use a new resolver instance
        call_user_func($test, ArgInspector::resolver(ArgInspector::tagReader()));

        // reuse the first resolver
        call_user_func($test, $resolver);
    }

    /**
     * @param Foo $foo [wire:genie]
     * @param int $theAnswer [wire:Dakujem\Tests\Bar]
     */
    public function methodFoo(Foo $foo, int $theAnswer)
    {
    }

    public static function methodBar(Foo $foo, int $theAnswer)
    {
    }

    public function otherMethod(Bar $bar, Foo $foo, bool $ok)
    {
    }
}
