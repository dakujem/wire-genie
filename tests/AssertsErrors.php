<?php

namespace Dakujem\Wire\Tests;

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
     * @param string $expectedException class name of the expected exception
     * @param null|int $expectedCode
     * @param null|string $expectedMessage
     */
    protected function assertException(
        callable $callable,
        $expectedException = 'Exception',
        $expectedMessage = null,
        $expectedCode = null
    ) {
        $expectedException = ltrim((string)$expectedException, '\\');
        if (!class_exists($expectedException) && !interface_exists($expectedException)) {
            $this->fail(sprintf('An exception of type "%s" does not exist.', $expectedException));
        }
        try {
            $callable();
        } catch (\Exception $e) {
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
            $this->assertInstanceOf($expectedException, $e, $errorMessage);
            if ($expectedMessage !== null) {
                $this->assertSame($expectedMessage, $message, sprintf('Failed asserting the message of thrown %s.', $class));
            }
            if ($expectedCode !== null) {
                $this->assertEquals($expectedCode, $code, sprintf('Failed asserting code of thrown %s.', $class));
            }
            return;
        }
        $errorMessage = 'Failed asserting that exception';
        if (strtolower($expectedException) !== 'exception') {
            $errorMessage .= sprintf(' of type %s', $expectedException);
        }
        $errorMessage .= ' was thrown.';
        $this->fail($errorMessage);
    }
}

