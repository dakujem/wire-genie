<?php

declare(strict_types=1);

namespace Dakujem\Tests;

use Dakujem\ArgInspector;
use Dakujem\DormantProvider;
use Dakujem\InvokableProvider;
use LogicException;
use PHPUnit\Framework\TestCase;

require_once 'AssertsErrors.php';

final class DormantProviderTest extends TestCase
{
    use AssertsErrors;

    public function testCallable(): void
    {
        $ip = new DormantProvider(function () {
            return [];
        });

        $this->assertIsCallable($ip, 'DormantProvider is not callable.');

        $hasBeenCalled = false;
        call_user_func($ip, function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
        });

        $this->assertTrue($hasBeenCalled);

        $hasBeenCalled = false;
        $ip->invoke(function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
        });

        $this->assertTrue($hasBeenCalled);
    }

    public function testResolverIsCalled(): void
    {
        $hasBeenCalled = false;
        $resolver = function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
            return [];
        };
        $ip = new DormantProvider($resolver);

        $ip->invoke(function () {
            // foo
        });

        $this->assertTrue($hasBeenCalled);
    }

    public function testArgumentsArePassedFromResolver(): void
    {
        $hasBeenCalled = false;
        $resolver = function () use (&$hasBeenCalled) {
            $hasBeenCalled = true;
            return [1, 2, 3, 42, 'foo'];
        };
        $ip = new DormantProvider($resolver);

        $args = $ip->invoke(function (...$args) {
            return $args;
        });
        $this->assertTrue($hasBeenCalled);
        $this->assertSame([1, 2, 3, 42, 'foo'], $args);
    }

    public function testExactArgumentsArePassed(): void
    {
        $args = [$object = new ArgInspector()];
        $ip = new DormantProvider(function () use ($args) {
            return $args;
        });
        $foo = $ip->invoke(function (ArgInspector $foo) {
            return $foo;
        });
        $this->assertSame($object, $foo);
    }

    public function testReturnTypeOfResolverIsChecked()
    {
        $func = function (...$args) {
            // foo
        };

        // this should pass okay
        (new DormantProvider(function () {
            return [];
        }))($func);

        // errors thrown
        $this->assertException(function () use ($func) {
            (new DormantProvider(function () {
                return null;
            }))($func);
        }, LogicException::class, 'The resolver must return an iterable type, NULL returned.');
        $this->assertException(function () use ($func) {
            (new DormantProvider(function () {
                return 42;
            }))($func);
        }, LogicException::class, 'The resolver must return an iterable type, integer returned.');
        $this->assertException(function () use ($func) {
            (new DormantProvider(function () {
                return new InvokableProvider([]);
            }))($func);
        }, LogicException::class, 'The resolver must return an iterable type, an instance of Dakujem\InvokableProvider returned.');
    }
}
