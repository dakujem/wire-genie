# Wire Genie - Dependency Provider

[![Build Status](https://travis-ci.org/dakujem/wire-genie.svg?branch=master)](https://travis-ci.org/dakujem/wire-genie)


> 💿 `composer require dakujem/wire-genie`

Allows to fetch multiple dependencies from a DI container
and provide them as arguments to callables.

> Disclaimer 🤚
>
> Depending on actual use, this might be breaking IoC
> and degrade your dependency injection container to a service locator,
> so use it with caution.
>
> But then again, if you can `get` from your container, you can use Wire Genie.


The main purpose is to provide limited means of wiring services
without exposing the service container itself.

## How it works
Wire genie fetches specified dependencies from a container
and passes them to a callable, returning the result.

```php
$factory = function( Dependency $dep1, OtherDependency $dep2, ... ){
    // create stuff...
    return new Service($dep1, $dep2, ... );
};

// create a provider specifying dependencies
$provider = $wireGenie->provide( Dependency::class, OtherDependency::class, ... );

// invoke the factory using the provider
$service = $provider->invoke($factory);
```

Note that _how_ one specifies the dependencies depends on the container he uses.\
It might be just string keys, class names or interfaces.\
Wire Genie calls PSR-11's `ContainerInterface::get()` and `ContainerInterface::has()` under the hood.


## Usage

```php
// Use any PSR-11 compatible container you like.
$container = AppContainerPopulator::populate(new Sleeve());

// Give Wire Genie full access to your DI container,
$genie = new WireGenie($container);

// or give it access to limited services only.
// (classes implementing RepositoryInterface in this example)
$repoGenie = new WireGenie(new WireLimiter($container, [
    RepositoryInterface::class,
    // you may whitelist multiple classes or interfaces
]));

// Create a factory function you would like to call, given the dependencies:
$factory = function(MyServiceInterface $s1, MyOtherSeviceInterface $s2){
    // do stuff, create other objects, system services and so on...
    return new ComplexService($s1, $s2);
};

// Wire genie fetches the dependencies you specify in the `provide` call
// and provides them to the callable when you call the `invoke` method:
$complexService = $genie->provide('myService', 'my-other-service')->invoke($factory);

// Repo genie will only be able to fetch repositories, the following fails:
$repoGenie->provide('my-system-service');
```

> 💡
>
> This approach solves an edge case in certain implementations where dependency injection
> boilerplate can not be avoided or reduced in a different way.
>
> Normally you want to wire your dependencies when building your app's DI container.

You now have means to allow a service
on-demand access to services of a certain type without injecting them all.\
This particular use-case breaks IoC, though.
```php
new RepositoryUser($repoGenie);

// inside RepositoryUser
$repoGenie->provide(
    ThisRepo::class,
    ThatRepo::class
)->invoke(function(
    ThisRepository $r1,
    ThatRepository $r2
){
    // do stuff with the repos...
});
```
> Note the _callable_ returned by `WireGenie::provide()` method and its immediate invocation.

As you can see, it is important to limit access
to certain services only to keep your app layers in good shape.


### Example pseudocode

More in-depth
```php
// Given a factory function like the following one:
$factoryFunction = function( /*...dependencies...*/ ){
    // do stuff or create stuff
    return new Service( /*...dependencies...*/ );
};

// Give access to full DI container
// or use WireLimiter to limit access to certain services only.
$genie = new WireGenie( $serviceContainer );

// A dependency identifier may be a string key or a class name,
// depending on your container implementation.
// Ath this point, the dependencies are resolved by the container.
$invokableProvider = $genie->provide( /*...dependency-identifier-list...*/ );

// Invoke the factory like this,
$service = $invokableProvider->invoke($factoryFunction);
// or like this.
$service = $invokableProvider($factoryFunction);
```

Shorthand syntax:
```php
$genie->provide( ... )($factoryFunction);
$genie->provide( ... )->invoke($factoryFunction);

$genie->provide( ... )(function( ... ){ ... });
$genie->provide( ... )->invoke(function( ... ){ ... });
```


## Contributing

Ideas or contribution is welcome. Please make a PR or file an issue.
