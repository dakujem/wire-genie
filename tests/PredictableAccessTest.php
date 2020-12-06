<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\Genie;
use Dakujem\Wire\Lamp;
use Dakujem\Wire\Limiter;
use Dakujem\Wire\PredictableAccess;
use Dakujem\Wire\Simpleton;
use Dakujem\Wire\TagBasedStrategy;
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
                function () use ($object) {
                    return $object->foo;
                },
                LogicException::class, sprintf('Invalid read of property \'%s::$foo\'.', get_class($object))
            );
            $this->assertException(
                function () use ($object) {
                    return $object->foo = 42;
                },
                LogicException::class, sprintf('Invalid write to property \'%s::$foo\'.', get_class($object))
            );
            $this->assertException(
                function () use ($object) {
                    return isset($object->foo);
                },
                LogicException::class, sprintf('Invalid read of property \'%s::$foo\'.', get_class($object))
            );
            $this->assertException(
                function () use ($object) {
                    unset($object->foo);
                },
                LogicException::class, sprintf('Invalid write to property \'%s::$foo\'.', get_class($object))
            );
        };
        $test(new Lamp($c = new Sleeve()));
        $test(new Genie($c = new Sleeve()));
        $test(new Limiter($c = new Sleeve(), []));
        $test(new Simpleton());
        $test(new TagBasedStrategy());
    }
}
