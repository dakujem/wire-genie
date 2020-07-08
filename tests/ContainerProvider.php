<?php

namespace Dakujem\Tests;

use Dakujem\Sleeve;
use Dakujem\WireGenie;
use Error;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * ContainerProvider
 */
class ContainerProvider
{
    public static function createContainer(): ContainerInterface
    {
        $sleeve = new Sleeve();

        $sleeve->set('self', $sleeve);

        $sleeve->set('genie', function (Sleeve $container) {
            return new WireGenie($container);
        });
        $sleeve->set(WireGenie::class, function (Sleeve $container) {
            return new WireGenie($container);
        });

        $sleeve->set('ref1', function () {
            return new ReflectionClass(WireGenie::class);
        });

        $sleeve->set(Error::class, $sleeve->factory(function () {
            return new Error('Hey! I\'m a new error. Nice to meet you.');
        }));

        $sleeve->set(Foo::class, function () {
            return new Foo;
        });

        $sleeve->set(Bar::class, function () {
            return new Bar;
        });
        $sleeve->set(Baz::class, function () {
            return new Baz;
        });

        return $sleeve;
    }
}

class Foo
{
}

class Bar
{
}

class Baz extends Bar
{
}

class NoConstructor
{
}

class HasConstructor
{
    public function __construct()
    {
    }
}

class InheritsConstructor extends HasConstructor
{

}

class WeepingWillow
{
    public $args;

    public function __construct(...$args)
    {
        $this->args = $args;
    }
}

class HollowWillow extends WeepingWillow
{
    public function __construct(Foo $foo)
    {
        parent::__construct($foo);
    }
}
