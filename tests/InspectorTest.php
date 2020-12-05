<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Wire\Inspector;
use PHPUnit\Framework\TestCase;

/**
 * @internal test
 */
final class InspectorTest extends TestCase
{
    public function testInspectConstructor(): void
    {
        $this->assertSame(HasConstructor::class, Inspector::reflectionOfConstructor(HasConstructor::class)->class);
        $this->assertSame(HasConstructor::class, Inspector::reflectionOfConstructor(InheritsConstructor::class)->class);
        $this->assertSame(null, Inspector::reflectionOfConstructor(NoConstructor::class));
    }
}
