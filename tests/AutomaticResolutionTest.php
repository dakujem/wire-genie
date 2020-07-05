<?php
declare(strict_types=1);

namespace Dakujem\Tests;

use Closure;
use Dakujem\WireGenie;
use Dakujem\WireLimiter;
use Error;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use ReflectionFunction;
use ReflectionParameter;
use RuntimeException;

require_once 'ContainerProvider.php';

// NOTE: this test is outdated, since v1.1 already ships with argument type resolution

final class TestArgumentReflector
{
    public static function types(Closure $closure): array
    {
        $rf = new ReflectionFunction($closure);
        return array_map(function (ReflectionParameter $rp): string {
            $type = ($rp->getClass())->name ?? null;
            if ($type === null) {
                throw new RuntimeException(sprintf('Unable to reflect type of parameter "%s".', $rp->getName()));
            }
            return $type;
        }, $rf->getParameters());
    }
}

/**
 * AutomaticResolutionTest
 */
final class AutomaticResolutionTest extends TestCase
{
    private function wireAndExecute(Closure $closure)
    {
        $genie = new WireGenie(ContainerProvider::createContainer());
        return $genie->provide(...TestArgumentReflector::types($closure))->invoke($closure);
    }

    public function testCorrectResolution(): void
    {
        $run = false;
        $this->wireAndExecute(function (?WireGenie $wg = null, ?Error $e = null) use (&$run) {
            $run = true;
            $this->assertSame(WireGenie::class, get_class($wg));
            $this->assertSame(Error::class, get_class($e));
        });
        $this->assertTrue($run);
    }

    public function testFailedResolution(): void
    {
        $run = false;
        $this->wireAndExecute(function (?WireLimiter $foo = null) use (&$run) {
            $run = true;
            $this->assertSame(null, $foo);
        });
        $this->assertTrue($run);
    }

    public function testFailedReflection(): void
    {
        $this->expectException(RuntimeException::class);
        $this->wireAndExecute(function (int $foo = null) {
        });
    }
}
