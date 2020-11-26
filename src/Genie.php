<?php

declare(strict_types=1);

namespace Dakujem\Wire;

use Dakujem\Wire\Exceptions\Unresolvable;
use Dakujem\Wire\Exceptions\UnresolvableCallArguments;
use Psr\Container\ContainerInterface as Container;

/**
 * Wire Genie - a magical invoker of callables and constructor of classes.
 *
 * By default, it uses the reflection API and leverages PHP 8 attributes, but can be configured otherwise.
 *
 * The class revolves around two methods and the default resolver strategy:
 * @see DefaultResolverStrategy
 * @see Genie::invoke()
 * @see Genie::construct()
 *
 * @author Andrej Rypák (dakujem) <xrypak@gmail.com>
 */
final class Genie implements Invoker, Constructor
{
    use PredictableAccess;

    /**
     * A callable resolver _strategy_.
     * This strategy allows to manipulate the way invocation and constructor arguments are resolved.
     * @var callable fn(Genie, callable|string $target, ...$staticArgs): iterable
     */
    private $core;

    /**
     * Construct an instance of Wire Genie. Really? Yup!
     *
     * According to the default resolver strategy,
     * the callable (or constructor) parameter types are detected using the PHP's reflection API.
     * Then, each of the parameters is mapped to an argument for the invocation,
     * leveraging the reflection API and PHP 8 attributes.
     * @see DefaultResolverStrategy
     *
     * The process can be altered not to work with reflection or attributes,
     * it's all up to the resolver strategy.
     *
     * @param Container $container a PSR-11 service container implementation
     * @param callable|null $core the resolver strategy; resolves call arguments for a given target;
     *                            MUST return an iterable type
     */
    public function __construct(
        private Container $container,
        ?callable $core = null,
    ) {
        $this->core = $core;
    }

    /**
     * Invokes the callable $target with automatically resolved arguments.
     * Unresolved arguments are filled in from the static argument pool.
     * Returns the result of the call.
     *
     * @param callable $target callable to be invoked
     * @param mixed ...$staticArgs static argument pool
     * @return mixed result of the $target callable invocation
     */
    public function invoke(callable $target, ...$staticArgs): mixed
    {
        return $target(...$this->resolveArguments($target, ...$staticArgs));
    }

    /**
     * Constructs the requested object with automatically resolved constructor arguments.
     * Unresolved arguments are filled in from the static argument pool.
     * Returns the constructed instance.
     *
     * @param string $target target class name
     * @param mixed ...$staticArgs static argument pool
     * @return mixed the constructed class instance
     */
    public function construct(string $target, ...$staticArgs): mixed
    {
        return new $target(...$this->resolveArguments($target, ...$staticArgs));
    }

    /**
     * This provider instances are also callable.
     *
     * @param callable|string $target callable to be invoked or a name of a class to be constructed.
     * @param mixed ...$staticArgs static argument pool
     * @return mixed result of the $target callable invocation or an instance of the requested class
     */
    public function __invoke(callable|string $target, ...$staticArgs): mixed
    {
        return is_string($target) && class_exists($target) ?
            $this->construct($target, ...$staticArgs) :
            $this->invoke($target, ...$staticArgs);
    }

    /**
     * Works like this:
     * - a detector produces a list of parameters to be resolved, given a function or a class name
     * - the list is passed to a mapper that will map it to a list of arguments
     * - the default mapper will iterate over the list of parameters and pass each item to the resolver
     *   along with the container instance and the next static argument
     * - the resolver is expected to return a value to be used as argument for each given parameter
     *
     * @param callable|string $target a callable to be invoked or a name of a class to be constructed
     * @param mixed ...$staticArguments static arguments to fill in for parameters where identifier can not be detected
     * @return iterable
     */
    private function resolveArguments(callable|string $target, ...$staticArguments): iterable
    {
        try {
            return ($this->core ?? new DefaultResolverStrategy())(
                $this,
                $target,
                ...$staticArguments,
            );
        } catch (Unresolvable $ex) {
            throw new UnresolvableCallArguments($ex);
        }
    }

    /**
     * Exposes the internal container to a callable.
     * A public getter for the container instance is not provided by design.
     *
     * @param callable $worker function(ContainerInterface $container)
     * @return mixed forwards the return value of the callable
     */
    public function exposeContainer(callable $worker): mixed
    {
        return $worker($this->container, $this);
    }

    /**
     * Create an instance of Genie
     * by passing either a EagerGenie or a container implementation instance.
     *
     * See the constructor for parameter explanation.
     * @see Genie::__construct()
     *
     * @param EagerGenie|self|Container $source
     * @param callable|null $core
     * @return self
     */
    public static function employ(EagerGenie|self|Container $source, ?callable $core = null): self
    {
        $worker = function (Container $container) use ($core): self {
            return new self($container, $core);
        };
        return $source instanceof EagerGenie || $source instanceof self ?
            $source->exposeContainer($worker) : $worker($source);
    }
}
