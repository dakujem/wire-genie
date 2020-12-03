<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Attributes\Wire;
use Dakujem\Wire\Genie;
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
        $func = function () {
            return func_get_args();
        };
        $this->assertSame([], $g->invoke($func));
        $this->assertSame([42], $g->invoke($func, 42));
        $this->assertSame(['foo', 'bar'], $g->invoke($func, 'foo', 'bar'));
    }

    protected function _InvokesAnyCallableTypeAndFillsInUnresolvedArguments(Genie $g)
    {
        $func = function (Plant $foo, int $theAnswer) {
            return [$foo, $theAnswer];
        };
        $invokable = new class {
            public function __invoke(Plant $foo, int $theAnswer)
            {
                return [$foo, $theAnswer];
            }
        };

        $check = function ($args) {
            $this->assertInstanceOf(Plant::class, $args[0]);
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

    #---------------------------------

    /**
     * Invokes a Closure and binds $this to the object given via the first parameter.
     *
     * @param string|object $objectOrClass
     * @param \Closure $closure
     * @return mixed
     */
    protected static function with(string|object $objectOrClass, \Closure $closure): mixed
    {
        return $closure->bindTo(is_object($objectOrClass) ? $objectOrClass : null, $objectOrClass)();
    }

    #---------------------------------

    public function methodFoo(Plant $foo, int $theAnswer): array
    {
        return func_get_args();
    }

    public static function methodBar(Plant $foo, int $theAnswer): array
    {
        return func_get_args();
    }

    /**
     * @param Animal $animal [wire:Dakujem\Wire\Tests\Sheep]
     * @param mixed $theAnswer [wire:genie]
     */
    public function methodTagOverride(Animal $animal, $theAnswer): array
    {
        return func_get_args();
    }

    public function methodAttributeOverride(#[Wire(Sheep::class)] Animal $animal, #[Wire('genie')] $theAnswer): array
    {
        return func_get_args();
    }
}
