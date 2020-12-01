<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Genie;
use Dakujem\Wire\TagBasedStrategy;
use Dakujem\WireGenie;
use Psr\Container\ContainerInterface;
use ReflectionFunctionAbstract;

require_once 'AssertsErrors.php';
require_once 'testHelperClasses.php';

/**
 * @internal test
 */
final class GenieWithTagBasedStrategyTest extends GenieBaseTest
{

    public function testInvokerFillsInArguments()
    {
        $invoker = new Genie(ContainerProvider::createContainer(), new TagBasedStrategy());
        $this->_FillsInArguments($invoker);
    }

    public function testInvokerInvokesAnyCallableTypeAndFillsInUnresolvedArguments()
    {
        $invoker = new Genie(ContainerProvider::createContainer(), new TagBasedStrategy());
        $this->_InvokesAnyCallableTypeAndFillsInUnresolvedArguments($invoker);
    }

    public function testInvokerReadsTagsByDefault()
    {
        $invoker = new Genie(ContainerProvider::createContainer(), new TagBasedStrategy());
        // tags should be read by default
        $rv = $invoker->invoke([$this, 'methodTagOverride'], 42);
        $this->assertCount(3, $rv);
        $this->assertInstanceOf(Baz::class, $rv[0]);
        $this->assertInstanceOf(WireGenie::class, $rv[1]);
        $this->assertSame(42, $rv[2]);
    }

    public function testAutomaticResolutionCanBeOverridden()
    {
        $invoker = new Genie($sleeve = ContainerProvider::createContainer(), new TagBasedStrategy());
        $func = function (Bar $bar) {
            return func_get_args();
        };
        // normally resolves to Bar instance
        $this->assertSame([$sleeve->get(Bar::class)], $invoker->invoke($func));

        /**
         * @param Bar $bar [wire:] <-- empty tag indicates no wiring
         * @return array
         */
        $funcOverridden = function (Bar $bar) {
            return func_get_args();
        };
        $baz = $sleeve->get(Baz::class);
        // but here we turn the detection off and provide our own instance (of Baz)
        $this->assertSame([$baz], $invoker->invoke($funcOverridden, $baz));
    }

    public function testConstructor()
    {
        $invoker = new Genie($sleeve = ContainerProvider::createContainer(), new TagBasedStrategy());
        $rv = $invoker->construct(WeepingWillow::class);
        $this->assertInstanceOf(WeepingWillow::class, $rv);
        $this->assertSame([], $rv->args);

        $rv = $invoker->construct(HollowWillow::class);
        $this->assertInstanceOf(HollowWillow::class, $rv);
        $this->assertSame([$sleeve->get(Foo::class)], $rv->args);
    }

    public function testInvalidInvocation1()
    {
        $invoker = new Genie(ContainerProvider::createContainer(), new TagBasedStrategy());

        // passes ok
        $invoker->invoke([$this, 'methodFoo'], 42);

        $this->expectErrorMessage(sprintf('Too few arguments to function %s::methodFoo(), 1 passed', parent::class));

        // type error, missing argument
        $invoker->invoke([$this, 'methodFoo']);
    }

    public function testInvalidInvocation2()
    {
        $invoker = new Genie(ContainerProvider::createContainer(), new TagBasedStrategy());

        $func = function (Foo $foo, int $theAnswer) {
            return [$foo, $theAnswer];
        };
        $func2 = function (Foo $foo, int $theAnswer = null) {
            return [$foo, $theAnswer];
        };

        // passes ok
        $invoker->invoke($func, 42);
        $invoker->invoke($func2);

        $this->expectErrorMessage(sprintf('Too few arguments to function %s::%s\{closure}(), 1 passed', self::class, __NAMESPACE__));

        // type error, missing argument
        $invoker->invoke($func);
    }

    public function testInvokerUsesCustomCallables()
    {
        $sleeve = ContainerProvider::createContainer();
        $detectorCalled = 0;
        $detector = function (ReflectionFunctionAbstract $ref) use (&$detectorCalled) {
            $detectorCalled += 1;
            return TagBasedStrategy::detectTypes($ref); // no tag reader
        };
        $proxyCalled = 0;
        $proxy = function ($id, ContainerInterface $container) use (&$proxyCalled) {
            $proxyCalled += 1;
            return $container->get($id);
        };
        $reflectorCalled = 0;
        $reflector = function ($target) use (&$reflectorCalled) {
            $reflectorCalled += 1;
            return TagBasedStrategy::reflectionOf($target);
        };
        $invoker = new Genie($sleeve, new TagBasedStrategy($detector, $proxy, $reflector));
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
            return TagBasedStrategy::detectTypes($ref, TagBasedStrategy::tagReader()); // added tag reader
        };
        $proxyCalled = 0;
        $proxy = function ($id, ContainerInterface $container) use (&$proxyCalled) {
            $proxyCalled += 1;
            return $container->get($id);
        };
        $reflectorCalled = 0;
        $reflector = function ($target) use (&$reflectorCalled) {
            $reflectorCalled += 1;
            return TagBasedStrategy::reflectionOf($target);
        };
        $invoker = new Genie($sleeve, new TagBasedStrategy($detector, $proxy, $reflector));
        [$baz, $genie, $fourtyTwo, $foo] = $invoker->invoke([$this, 'methodTagOverride'], 42, 'foobar');
        $this->assertSame(1, $reflectorCalled);
        $this->assertSame(1, $detectorCalled);
        $this->assertSame(2, $proxyCalled);
        $this->assertSame($sleeve->get(Baz::class), $baz); // Baz, not Bar !
        $this->assertSame($sleeve->get('genie'), $genie);
        $this->assertSame(42, $fourtyTwo); // rest arguments trail
        $this->assertSame('foobar', $foo); // rest arguments trail
    }
}
