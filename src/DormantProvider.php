<?php

declare(strict_types=1);

namespace Dakujem;

/**
 * A provider that resolves call arguments at the moment of invocation.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
class DormantProvider implements Invoker
{
    use PredictableAccess;

    private $resolver;

    /**
     * @param callable $resolver A callable that will resolve call arguments.
     */
    public function __construct(callable $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Invokes the callable $target with arguments returned by the resolver passed to the constructor.
     * Returns the result of the call.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target)
    {
        $args = call_user_func($this->resolver, $target);
        return call_user_func($target, ...$args);
    }

    /**
     * The provider instances are also callable.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function __invoke(callable $target)
    {
        return $this->invoke($target);
    }
}
