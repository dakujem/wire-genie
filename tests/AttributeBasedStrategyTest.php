<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\AttributeBasedStrategy;
use Dakujem\Wire\Exceptions\InvalidConfiguration;
use Dakujem\Wire\Genie;
use PHPUnit\Framework\TestCase;

/**
 * @internal test
 */
class AttributeBasedStrategyTest extends TestCase
{
    use AssertsErrors;

    public function testExceptionWhenDetectorReturnsWrongType()
    {
        $core = AttributeBasedStrategy::core(
            fn(): iterable => ['foo'],
            AttributeBasedStrategy::defaultResolver()
        );
        $this->assertException(
            fn() => $core(new Genie(new Sleeve([])), Sheep::class),
            InvalidConfiguration::class,
            "The detector produced a collection containing an invalid element of type 'string' but only instances of ReflectionParameter are accepted."
        );
    }
}
