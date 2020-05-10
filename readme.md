# Wire Genie - Dependency Provider

> This is an early DRAFT.

Allows to fetch multiple dependencies from a DI container
and provide them as arguments to a callable.

> Disclaimer
>
> Depending on actual use, this might be breaking IoC
> and degrade your dependency-injection container to a service locator,
> so use with caution.
>
> But then again, if you can `get` from your container, you can use wire genie.


## Usage

The main purpose is to provide limited means of wiring services without exposing the service container itself.\
It solves an edge case in certain implementations where dependency injection
boilerplate can not be avoided in a different way.\
Normally you want to wire your dependencies when building your app's DI container.


Wire genie fetches the dependencies you specify from a container and passes them to the callable, returning the result.
```php
$container = ContainerPopulator::populate(new Sleeve()); // you any PSR-11 compatible container you like

// this factory function is what you would like to call, given the dependencies:
$callable = function(MyService $s1, MyOtherSevice $s2){
    // do stuff, create other objects, system services and so on...
    return new ComplexService($s1, $s2);
};

// wire genie fetches the dependencies and provides them to the callable and executes it:
$complexService = $wg->provide('myService', 'my-other-service')($callable);
```

**Real use case #1**\
Allow a service access to all services of certain type without injecting them all.

```php
// give access to all "repositories", given they all implement RepositoryInterface
$repoGenie = new WireGenie(new WireLimiter($container, [
    RepositoryInterface::class
]));
```
You can now access or provide to further calls any repository using the genie:
```php
$repoGenie->provide(ThisRepository::class, ThatRepository::class)(function(ThisRepository $r1, ThatRepository $r2){
    // do stuff with the repos...
});
```

As you can see, it is important to limit access to certain services to keep your app layers in good shape.



