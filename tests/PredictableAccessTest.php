<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\AttributeBasedStrategy;
use Dakujem\Wire\Genie;
use Dakujem\Wire\Lamp;
use Dakujem\Wire\Limiter;
use Dakujem\Wire\PredictableAccess;
use Dakujem\Wire\Simpleton;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @see PredictableAccess
 *
 * @internal test
 */
final class PredictableAccessTest extends TestCase
{
    use AssertsErrors;

    public function testRubbing(): void
    {
        $test = function (object $object) {
            $this->assertException(
                fn() => $object->foo,
                LogicException::class, sprintf('Invalid read of property \'%s::$foo\'.', $object::class)
            );
            $this->assertException(
                fn() => $object->foo = 42,
                LogicException::class, sprintf('Invalid write to property \'%s::$foo\'.', $object::class)
            );
            $this->assertException(
                fn() => isset($object->foo),
                LogicException::class, sprintf('Invalid read of property \'%s::$foo\'.', $object::class)
            );
            $this->assertException(
                function () use ($object) {
                    unset($object->foo);
                },
                LogicException::class, sprintf('Invalid write to property \'%s::$foo\'.', $object::class)
            );
        };
        $test(new Lamp($c = new Sleeve()));
        $test(new Genie($c = new Sleeve()));
        $test(new Limiter($c = new Sleeve(), []));
        $test(new Simpleton());
        $test(new AttributeBasedStrategy());
    }
}
