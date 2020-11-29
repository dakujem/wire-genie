<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\ArgInspector;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

require_once 'AssertsErrors.php';

/**
 * @internal test
 */
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
        $this->assertSame([Foo::class, null, Bar::class], $types); // trailing null trimmed
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
        $this->assertSame([Foo::class, null, Bar::class], $types); // trailing null trimmed
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

    public function testEmptyWireTagIndicatesNoWiring(): void
    {
        /**
         * @param Foo $foo [wire:]
         * @param int $qux
         * @param Bar $bar [wire:]
         * @param $wham
         */
        $closure = function (Foo $foo, int $qux, Bar $bar, $wham) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = ArgInspector::detectTypes($reflection, ArgInspector::tagReader());
        $this->assertSame([], $types); // trailing null values trimmed, all in this case
    }

    public function testTypeDetector(): void
    {
        $reflection = new ReflectionMethod($this, 'otherMethod');
        $detector = ArgInspector::typeDetector();
        $this->assertSame([Foo::class, null, Bar::class], $detector($reflection)); // trailing null is trimmed !
    }

    public function testTypeDetectorWithTags(): void
    {
        $reflection = new ReflectionMethod($this, 'otherMethod');
        $detector = ArgInspector::typeDetector(ArgInspector::tagReader());
        $this->assertSame([Foo::class, null, 'overridden', \My\Name\Space\Wham::class], $detector($reflection));
    }

    public function testTagDetector(): void
    {
        $reflection = new ReflectionMethod($this, 'otherMethod');
        $detector = ArgInspector::tagDetector();
        $this->assertSame([null, null, 'overridden', \My\Name\Space\Wham::class], $detector($reflection));
    }

    public function testReflectionOfCallables()
    {
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOfCallable(function () {
        }));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOfCallable(new class {
            public function __invoke()
            {
            }
        }));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOfCallable('\fopen'));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOfCallable([$this, 'methodFoo']));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOfCallable(self::class . '::methodBar'));
    }

    public function testReflectionOfConstructors()
    {
        $this->assertSame(null, ArgInspector::reflectionOfConstructor(NoConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOfConstructor(HasConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOfConstructor(InheritsConstructor::class));
    }

    public function testReflectionOf()
    {
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf('\fopen'));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf([$this, 'methodFoo']));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf(self::class . '::methodBar'));
        $this->assertSame(null, ArgInspector::reflectionOf(NoConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf(HasConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, ArgInspector::reflectionOf(InheritsConstructor::class));
    }

    public function testTagParsing()
    {
        /**
         * @param $foo [wire:foobar]
         * @param Barbar $bAr [wire:whatever-it-is-you-like]
         * @param Foo|Bar|null $qux [wire:1234]
         * @param this is invalid $wham [wire:Name\Space\Wham]
         * @param $facepalm [other:tag-prece:ding] [wire:Dakujem\Tests\Foo] [and:another]
         * @param $empty [wire:]
         * @param self $notag
         */
        $callable = function () {
        };
        $annotations = ArgInspector::parseWireTags(ArgInspector::reflectionOfCallable($callable));
        $this->assertSame([
            'foo' => 'foobar',
            'bAr' => 'whatever-it-is-you-like',
            'qux' => '1234',
            'wham' => 'Name\Space\Wham',
            'facepalm' => 'Dakujem\Tests\Foo',
            'empty' => '',
            'notag' => null,
        ], $annotations);
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

    /**
     * @param Foo $foo [wire]
     * @param int $qux [notreally:nothing-sorry]
     * @param Bar $bar [wire:overridden]
     * @param $wham [wire:My\Name\Space\Wham]
     * @param $ham [wire:redundant]
     */
    private function otherMethod(Foo $foo, int $qux, Bar $bar, $wham)
    {
    }
}
