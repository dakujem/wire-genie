<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Genie;
use Dakujem\Wire\TagBasedStrategy;
use PHPUnit\Framework\TestCase;

require_once 'AssertsErrors.php';
require_once 'testHelperClasses.php';

/**
 * @internal test
 */
abstract class GenieBaseTest extends TestCase
{
    use AssertsErrors;


    protected function _FillsInArguments(Genie $g)
    {
        $g = new Genie(ContainerProvider::createContainer(), new TagBasedStrategy());
        $func = function () {
            return func_get_args();
        };
        $this->assertSame([], $g->invoke($func));
        $this->assertSame([42], $g->invoke($func, 42));
        $this->assertSame(['foo'], $g->invoke($func, 'foo'));
    }

    protected function _InvokesAnyCallableTypeAndFillsInUnresolvedArguments(Genie $g)
    {
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

        $rv = $g->invoke($func, 42);
        $check($rv);
        $rv = $g->invoke($invokable, 42);
        $check($rv);
        $rv = $g->invoke([$this, 'methodFoo'], 42);
        $check($rv);
        $rv = $g->invoke('\sleep', 0);
        $this->assertSame(0, $rv); // sleep returns 0 on success
        $rv = $g->invoke(self::class . '::methodBar', 42);
        $check($rv);
    }

    /**
     * Invokes a Closure and binds $this to the object given via the first parameter.
     *
     * @param string|object $objectOrClass
     * @param \Closure $closure
     * @return mixed
     */
    private static function with(string|object $objectOrClass, \Closure $closure): mixed
    {
        return $closure->bindTo(is_object($objectOrClass) ? $objectOrClass : null, $objectOrClass)();
    }

//    public function testGenieUsesCorrectDefault()
//    {
//        $invoker = new Genie(ContainerProvider::createContainer());
//        $self = $this;
//        $this->with($invoker, function () use ($self) {
//            $self->assertInstanceOf(AttributeBasedStrategy::class, $this->core);
//        });
//    }



    #---------------------------------

    public function methodFoo(Foo $foo, int $theAnswer): array
    {
        return func_get_args();
    }

    public static function methodBar(Foo $foo, int $theAnswer): array
    {
        return func_get_args();
    }

    /**
     * @param Bar $bar [wire:Dakujem\Wire\Tests\Baz]
     * @param mixed $theAnswer [wire:genie]
     */
    public function methodTagOverride(Bar $bar, $theAnswer): array
    {
        return func_get_args();
    }
}
