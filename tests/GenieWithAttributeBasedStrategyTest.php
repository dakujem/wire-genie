<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Exceptions\UnresolvableArgument;
use Dakujem\Wire\Genie;

require_once 'AssertsErrors.php';
require_once 'testHelperClasses.php';

/**
 * @internal test
 */
final class GenieWithAttributeBasedStrategyTest extends GenieBaseTest
{
    public function testInvokerFillsInArguments()
    {
        $invoker = new Genie(ContainerProvider::createContainer());
        $this->_FillsInArguments($invoker);
    }

    public function testInvokerInvokesAnyCallableTypeAndFillsInUnresolvedArguments()
    {
        $invoker = new Genie(ContainerProvider::createContainer());
        $this->_InvokesAnyCallableTypeAndFillsInUnresolvedArguments($invoker);
    }

    public function testInvokerReadsTagsByDefault()
    {
        $g = new Genie(ContainerProvider::createContainer());
        // tags should be read by default
        $rv = $g->invoke([$this, 'methodAttributeOverride'], 42);

        $this->assertCount(3, $rv);
        $this->assertInstanceOf(Baz::class, $rv[0]);
        $this->assertInstanceOf(Genie::class, $rv[1]);
        $this->assertSame(42, $rv[2]);
    }

    public function testInvalidInvocation()
    {
        $g = new Genie(ContainerProvider::createContainer());

        // passes ok
        $g->invoke([$this, 'methodFoo'], 42);

        // unresolvable argument
        $this->assertException(
            function () use ($g) {
                $g->invoke([$this, 'methodFoo']);
            },
            UnresolvableArgument::class,
            'Unresolvable: \'theAnswer\'.'
        );

        $func = function (Foo $foo, int $theAnswer) {
            return [$foo, $theAnswer];
        };
        $func2 = function (Foo $foo, int $theAnswer = null) {
            return [$foo, $theAnswer];
        };

        // passes ok
        $g->invoke($func, 42);
        $g->invoke($func2);

        // unresolvable argument
        $this->assertException(
            function () use ($g, $func) {
                $g->invoke($func);
            },
            UnresolvableArgument::class,
            'Unresolvable: \'theAnswer\'.'
        );
    }
}
