<?php

namespace Dakujem\Examples;

use Dakujem\WireGenie;
use Dakujem\WireInvoker;

/**
 * An EXAMPLE of a helper class that allows for simple automatic dependency injection for callables.
 *
 * Usage:
 *  $helper = new WireHelper(new WireGenie($diContainer));
 *  $result = $helper->wiredCall($anyFactoryFunction);
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class WireHelper
{
    /** @var WireGenie */
    private $genie;

    public function __construct(WireGenie $genie)
    {
        $this->genie = $genie;
    }

    /**
     * Invokes a callable resolving its type-hinted arguments,
     * filling in the unresolved arguments from the static argument pool.
     * Returns the callable's return value.
     * Reading "wire" tags is enabled.
     *
     * @param callable $code
     * @param mixed ...$staticArguments
     * @return mixed the callable's return value
     */
    public function wiredCall(callable $code, ...$staticArguments)
    {
        return WireInvoker::employ(
            $this->genie
        )->invoke($code, ...$staticArguments);
    }

    /**
     * Creates an instance of requested class, resolving its type-hinted constructor arguments,
     * filling in the unresolved arguments from the static argument pool.
     * Returns the constructed class instance.
     * Reading "wire" tags is enabled.
     *
     * @param callable $code
     * @param mixed ...$staticArguments
     * @return mixed the constructed class instance
     */
    public function wiredConstruct(string $className, ...$staticArguments)
    {
        return WireInvoker::employ(
            $this->genie
        )->construct($className, ...$staticArguments);
    }

    /**
     * Invokes a callable resolving explicitly given dependencies to call arguments.
     * Returns the callable's return value.
     *
     * @param callable $code
     * @param string[] ...$dependencies list of service names
     * @return mixed the callable's return value
     */
    public function wiredExplicitCall(callable $code, ...$dependencies)
    {
        return $this->genie->provide(...$dependencies)->invoke($code);
    }
}
