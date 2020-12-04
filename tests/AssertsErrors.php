<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Throwable;

/**
 * AssertsErrors
 *
 * @author Andrej Rypak <xrypak@gmail.com>
 */
trait AssertsErrors
{
    /**
     * Assert an error of a given type is thrown in the provided callable.
     * Can also check for a specific exception code and/or message.
     *
     * This assertion method fills in the hole in PHPUnit,
     * that is, it replaces the need for using expectException.
     *
     * @link https://gist.github.com/VladaHejda/8826707 [source]
     *
     * @param callable $callable
     * @param string|null $expectedThrowable class name of the expected exception
     * @param null|int $expectedCode
     * @param null|string $expectedMessage
     */
    protected function assertException(
        callable $callable,
        ?string $expectedThrowable = null,
        ?string $expectedMessage = null,
        ?int $expectedCode = null
    ) {
        $expectedThrowable = $expectedThrowable !== null ? ltrim((string)$expectedThrowable, '\\') : Throwable::class;
        if (!class_exists($expectedThrowable) && !interface_exists($expectedThrowable)) {
            $this->fail(sprintf('An exception of type "%s" does not exist.', $expectedThrowable));
        }
        try {
            $callable();
        } catch (Throwable $e) {
            $class = get_class($e);
            $message = $e->getMessage();
            $code = $e->getCode();
            $errorMessage = 'Failed asserting the class of exception';
            if ($message && $code) {
                $errorMessage .= sprintf(' (message was %s, code was %d)', $message, $code);
            } elseif ($code) {
                $errorMessage .= sprintf(' (code was %d)', $code);
            }
            $errorMessage .= '.';
            $this->assertInstanceOf($expectedThrowable, $e, $errorMessage);
            if ($expectedMessage !== null) {
                $this->assertSame($expectedMessage, $message, sprintf('Failed asserting the message of thrown %s.', $class));
            }
            if ($expectedCode !== null) {
                $this->assertEquals($expectedCode, $code, sprintf('Failed asserting code of thrown %s.', $class));
            }
            return;
        }
        $errorMessage = 'Failed asserting that exception';
        if (strtolower($expectedThrowable) !== 'exception') {
            $errorMessage .= sprintf(' of type %s', $expectedThrowable);
        }
        $errorMessage .= ' was thrown.';
        $this->fail($errorMessage);
    }
}

