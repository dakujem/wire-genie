<?php

declare(strict_types=1);

namespace App\Presenters;

use Contributte\Psr11\Container;
use Dakujem\WireGenie;
use Nette;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{
    /** @var WireGenie */
    protected $wireGenie;

    public function injectWireGenie(Nette\DI\Container $ndic): void
    {
        $this->wireGenie = new WireGenie(new Container($ndic));
    }

    protected function wire(...$args): callable
    {
        return $this->wireGenie->provide(...$args);
    }
}
