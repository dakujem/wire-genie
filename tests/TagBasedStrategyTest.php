<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\TagBasedStrategy;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

require_once 'AssertsErrors.php';

/**
 * @internal test
 */
final class TagBasedStrategyTest extends TestCase
{
    use AssertsErrors;

    public function testDetectTypes(): void
    {
        $closure = function (Foo $foo, Bar $bar) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = TagBasedStrategy::detectTypes($reflection);
        $this->assertSame([Foo::class, Bar::class], $types);
    }

    public function testDetectTypesWithAScalar(): void
    {
        $closure = function (Foo $foo, int $qux, Bar $bar, $wham) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = TagBasedStrategy::detectTypes($reflection);
        $this->assertSame([Foo::class, null, Bar::class], $types); // trailing null trimmed
    }

    public function testDetectTypesWithoutParameters(): void
    {
        $closure = function () {
        };
        $reflection = new ReflectionFunction($closure);
        $this->assertSame([], TagBasedStrategy::detectTypes($reflection));
    }

    public function testDetectTypesUsingTagReader(): void
    {
        $closure = function (Foo $foo, int $qux, Bar $bar, $wham) {
        };
        $reflection = new ReflectionFunction($closure);
        $types = TagBasedStrategy::detectTypes($reflection, TagBasedStrategy::tagReader());
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
        $types = TagBasedStrategy::detectTypes($reflection, TagBasedStrategy::tagReader());
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
        $types = TagBasedStrategy::detectTypes($reflection, TagBasedStrategy::tagReader());
        $this->assertSame([], $types); // trailing null values trimmed, all in this case
    }

    public function testTypeDetector(): void
    {
        $reflection = new ReflectionMethod($this, 'otherMethod');
        $detector = TagBasedStrategy::typeDetector();
        $this->assertSame([Foo::class, null, Bar::class], $detector($reflection)); // trailing null is trimmed !
    }

    public function testTypeDetectorWithTags(): void
    {
        $reflection = new ReflectionMethod($this, 'otherMethod');
        $detector = TagBasedStrategy::typeDetector(TagBasedStrategy::tagReader());
        $this->assertSame([Foo::class, null, 'overridden', \My\Name\Space\Wham::class], $detector($reflection));
    }

    public function testTagDetector(): void
    {
        $reflection = new ReflectionMethod($this, 'otherMethod');
        $detector = TagBasedStrategy::tagDetector();
        $this->assertSame([null, null, 'overridden', \My\Name\Space\Wham::class], $detector($reflection));
    }

    public function testReflectionOfCallables()
    {
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOfCallable(function () {
        }));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOfCallable(new class {
            public function __invoke()
            {
            }
        }));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOfCallable('\fopen'));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOfCallable([$this, 'methodFoo']));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOfCallable(self::class . '::methodBar'));
    }

    public function testReflectionOfConstructors()
    {
        $this->assertSame(null, TagBasedStrategy::reflectionOfConstructor(NoConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOfConstructor(HasConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOfConstructor(InheritsConstructor::class));
    }

    public function testReflectionOf()
    {
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOf('\fopen'));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOf([$this, 'methodFoo']));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOf(self::class . '::methodBar'));
        $this->assertSame(null, TagBasedStrategy::reflectionOf(NoConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOf(HasConstructor::class));
        $this->assertInstanceOf(ReflectionFunctionAbstract::class, TagBasedStrategy::reflectionOf(InheritsConstructor::class));
    }

    public function testTagParsing()
    {
        /**
         * @param $foo [wire:foobar]
         * @param Barbar $bAr [wire:whatever-it-is-you-like]
         * @param Foo|Bar|null $qux [wire:1234]
         * @param this is invalid $wham [wire:Name\Space\Wham]
         * @param $facepalm [other:tag-prece:ding] [wire:Dakujem\Wire\Tests\Foo] [and:another]
         * @param $empty [wire:]
         * @param self $notag
         */
        $callable = function () {
        };
        $annotations = TagBasedStrategy::parseWireTags(TagBasedStrategy::reflectionOfCallable($callable));
        $this->assertSame([
            'foo' => 'foobar',
            'bAr' => 'whatever-it-is-you-like',
            'qux' => '1234',
            'wham' => 'Name\Space\Wham',
            'facepalm' => 'Dakujem\Wire\Tests\Foo',
            'empty' => '',
            'notag' => null,
        ], $annotations);
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
        $arguments = TagBasedStrategy::resolveServicesFillingInStaticArguments($identifiers, $provider, $staticArguments);
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
        $arguments = TagBasedStrategy::resolveServicesFillingInStaticArguments($identifiers, $provider, $staticArguments);
        $this->assertSame([
            'foo',
            'foobar',
            'bar',
            null,
            'wham',
        ], $arguments);
    }

    /**
     * @param Foo $foo [wire:genie]
     * @param int $theAnswer [wire:Dakujem\Wire\Tests\Bar]
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
