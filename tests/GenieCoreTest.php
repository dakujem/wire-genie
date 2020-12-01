<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Genie;
use PHPUnit\Framework\TestCase;

require_once 'testHelperClasses.php';

/**
 * @internal test
 */
class GenieCoreTest extends TestCase
{
    public function testGenieUsesACoreProperlyPassingArguments()
    {
        $passed = [];
        $core = function (Genie $g, string|callable $t, ...$args) use (&$passed): iterable {
            $c = $g->exposeContainer(fn($c) => $c);
            $passed = [$g, $t, $args, $c];
            return [];
        };

        $sleeve = ContainerProvider::createContainer();
        $genie = new Genie($sleeve, $core);
        $target = fn() => 'ok';

        $ok = $genie->invoke($target, 'foobar');

        $this->assertSame('ok', $ok);
        $this->assertSame($genie, $passed[0]);
        $this->assertSame($target, $passed[1]);
        $this->assertSame(['foobar'], $passed[2]);
        $this->assertSame($sleeve, $passed[3]);
    }
}
