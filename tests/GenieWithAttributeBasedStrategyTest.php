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
            'theAnswer'
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
            'theAnswer'
        );
    }
}
