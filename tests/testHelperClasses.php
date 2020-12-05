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

class Animal
{
}

class Plant
{
}

class Sheep extends Animal
{
}

class Wolf extends Animal
{
}

class Frog extends Animal
{
}

class Lion extends Animal
{
}

class Elephant extends Animal
{
}

class NoConstructor
{
}

class HasConstructor
{
    public $animal = null;

    public function __construct(Animal $animal)
    {
        $this->animal = $animal;
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

class Thing
{
}

interface ServiceInterface
{
}

class MyService implements ServiceInterface
{
    public Thing $thing;
    public $foo;

    public function __construct(Thing $thing, $foo)
    {
        $this->thing = $thing;
        $this->foo = $foo;
    }
}

class MyOtherService implements ServiceInterface
{
    public Thing $thing;

    public function __construct(Thing $thing)
    {
        $this->thing = $thing;
    }
}

interface Expression
{
}

class Formula implements Expression
{
}

/**
 * 0
 */
class Zero extends Formula
{
}

/**
 * 4X
 */
class Coefficient extends Formula
{
    public function __construct(private float $factor)
    {
    }
}

/**
 * X + 2
 */
class Constant extends Formula
{
    public function __construct(private float $value)
    {
    }
}

/**
 * 4X + 2
 */
class Offset extends Formula
{
    public function __construct(private Constant $absolute, private Coefficient $relative)
    {
    }
}

