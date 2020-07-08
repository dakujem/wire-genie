<?php

namespace Dakujem\Examples;

use Dakujem\ArgInspector;
use Dakujem\WireGenie;

/**
 * An example of a helper class that allows for simple automatic dependency injection for callables.
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
     * Using "wire" tags is enabled.
     *
     * @param callable $code
     * @param mixed ...$staticArguments
     * @return mixed the callable's return value
     */
    public function wiredCall(callable $code, ...$staticArguments)
    {
        return
            $this->genie
                ->employ(ArgInspector::resolver(ArgInspector::tagReader()), ...$staticArguments)
                ->invoke($code);
    }
}
