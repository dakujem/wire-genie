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

        return $sleeve;
    }
}
