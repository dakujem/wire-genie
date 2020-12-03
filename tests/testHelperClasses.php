<?php

declare(strict_types=1);

namespace Dakujem\Wire\Tests;

use Dakujem\Sleeve;
use Dakujem\Wire\Genie;
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
            return new Genie($container);
        });
        $sleeve->set(Genie::class, function (Sleeve $container) {
            return new Genie($container);
        });

        $sleeve->set('ref1', function () {
            return new ReflectionClass(Genie::class);
        });

        $sleeve->set(Error::class, $sleeve->factory(function () {
            return new Error('Hey! I\'m a new error. Nice to meet you.');
        }));

        $sleeve->set(Plant::class, function () {
            return new Plant;
        });

        $sleeve->set(Animal::class, function () {
            return new Animal;
        });
        $sleeve->set(Sheep::class, function () {
            return new Sheep;
        });

        return $sleeve;
    }
}

class Plant
{
}

class Animal
{
}

class Sheep extends Animal
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
    public function __construct(Plant $foo)
    {
        parent::__construct($foo);
    }
}
