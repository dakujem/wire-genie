<?php

declare(strict_types=1);

namespace Dakujem;

use LogicException;

/**
 * A provider that resolves call arguments at the moment of invocation.
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
class DormantProvider implements Invoker, Constructor
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
        if (!is_iterable($args)) {
            throw new LogicException(sprintf(
                'The resolver must return an iterable type, %s returned.',
                is_object($args) ? 'an instance of ' . get_class($args) : gettype($args)
            ));
        }
        return call_user_func($target, ...$args);
    }

    public function construct(string $target)
    {
        $args = call_user_func($this->resolver, $target);
        if (!is_iterable($args)) {
            throw new LogicException(sprintf(
                'The resolver must return an iterable type, %s returned.',
                is_object($args) ? 'an instance of ' . get_class($args) : gettype($args)
            ));
        }
    }

    /**
     * This provider instances are also callable.
     *
     * @param callable $target callable to be invoked
     * @return mixed result of the $target callable invocation
     */
    public function __invoke(callable $target)
    {
        return $this->invoke($target);
    }
}
