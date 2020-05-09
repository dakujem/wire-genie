<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette\Application\UI\Form;
use Nette\Database\Connection;
use Nette\Routing\Router;

final class HomepagePresenter extends BasePresenter
{
    public function renderDefault(): void
    {
        $this->template->anyVariable = 'any value';

        $this->wire(Router::class, Connection::class)(function (Router $router, Connection $con) {
            // do stuff
            bdump($router);
            bdump($con);
        });
    }

    protected function createComponentMyForm()
    {
        return $this->wire(
            MyServiceInterface::class,
            MyOtherServiceInterface::class
        )(function (
            MyServiceInterface $a,
            MyOtherServiceInterface $b
        ) {
            return MyFormFactory::populate(new Form(), $a, $b, $this->localDependency);
        });
    }
}
