# Wire Genie - Dependency Provider

[![Build Status](https://travis-ci.org/dakujem/wire-genie.svg?branch=master)](https://travis-ci.org/dakujem/wire-genie)


> 💿 `composer require dakujem/wire-genie`

Allows to fetch multiple dependencies from a DI container
and provide them as arguments to callables.

> Disclaimer 🤚
>
> Improper use of this package might break established IoC principles
> and degrade your dependency injection container to a service locator,
> so use the package with caution.


The main purpose of the package is to provide limited means of wiring services
without exposing the service container itself.

Automatic dependency injection can be achieved, if configured properly.


## How it works

Wire genie fetches specified dependencies from a container
and passes them to a callable, invoking it and returning the result.

```php
$factory = function( Dependency $dep1, OtherDependency $dep2, ... ){
    // create stuff...
    return new Service($dep1, $dep2, ... );
};

// create a provider, explicitly specifying dependencies
$provider = $wireGenie->provide( Dependency::class, OtherDependency::class, ... );

// invoke the factory using the provider
$service = $provider->invoke($factory);
```

Note that _how_ services in the container are accessed depends on the conventions used.\
Services might be accessed by plain string keys, class names or interface names.\
Wire Genie simply calls PSR-11's `ContainerInterface::get()` and `ContainerInterface::has()` under the hood,
there is no other "magic".\
In the example above, services are accessed using their class names.


## Usage

> Note: In the following example, services are accessed using plain string keys.

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

// Repo genie will only be able to fetch repositories,
// the following call would fail
// if 'my-system-service' service did not implement RepositoryInterface:
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
This particular use-case breaks IoC if misused, though.
```php
// using $repoGenie from the previous snippet
new RepositoryConsumer($repoGenie);

// inside RepositoryConsumer
$repoGenie->provide(
    ThisRepo::class, // or 'this' ✳
    ThatRepo::class  // or 'that' ✳
)->invoke(function(
    ThisRepository $r1,
    ThatRepository $r2
){
    // do stuff with the repos...
});
```
> ✳ the actual keys depend on the container in use
> and the way the services are accessed.\
> These identifiers are bare strings without their own semantics.
> They may or may not be related to the actual instances that are fetched from the container.

In use cases like the one above, it is important to limit access
to certain services only, to keep your app layers in good shape.


## Automatic dependency resolution

Wire Genie package also comes with a helper class that enables automatic resolution of callable arguments.

If you find the explicit way too verbose, it is possible to omit defining the arguments, provided the arguments can be resolved using reflection:
```php
$genie->employ(ArgInspector::resolver())->invoke(function( Dependency $dep1, OtherDependency $dep2 ){
   return new Service($dep1, $dep2);
});
```

The resolver will make sure that `Dependency::class` and `OtherDependency::class`
are fetched from the container,
provided the services are accessible using their class names.

In case services are accessed by plain string identifiers, doc-comments and "tags" can be used:
```php
/**
 * @param $dep1 [wire:my-identifier]
 *              \__________________/
 *                the whole "wire tag"
 *
 * @param $dep2 [wire:other-identifier]
 *                    \______________/
 *                      service identifier
 */
$factory = function( Dependency $dep1, OtherDependency $dep2 ){
  return new Service($dep1, $dep2);
};
$genie->employ(ArgInspector::resolver(ArgInspector::tagReader()))->invoke($factory);
```
In this case, services registered as `my-identifier` and `other-identifier` are fetched from the container.

You might consider implementing an invoker helper class with a method like the following:
```php
/**
 * Invokes a callable resolving its type-hinted arguments,
 * filling in the unresolved arguments from the static argument pool.
 * Returns the callable's return value.
 * Using "wire" tags is enabled.
 */ 
public function wiredCall(callable $code, ...$staticDependencies)
{
    return $this->wireGenie->employ(
        ArgInspector::resolver(ArgInspector::tagReader()),
        ...$staticDependencies
    )->invoke($code);
}
```

> Note that using reflection might have negative performance impact
> if used heavily.

Automatic argument resolution is useful for:
- async job execution
    - supplying dependencies after a job is deserialized from a queue
- method dependency injection
    - for controller methods, where dependencies differ between the handler methods
- generic factories that create instances with varying dependencies


## Advanced

### Implementing custom logic around `WireGenie`'s core

`WireGenie::employ()` method enables implementing custom resolution of dependencies
and a custom way of fetching the services from your service container.

For exmaple, if every service was accessed by its class name,
except the backslashes `\` were replaced by dots '.' and in lower case,
you could implement the following to invoke `$target` callable:
```php
$resolver = function(array $deps, Container $container, callable $target): array {
    return array_map(function($dep) use ($container) {
        $key = str_replace('\\', '.', strtolower($dep)); // alter the service key
        return $container->has($key) ? $container->get($key) : null;
    }, $deps);
};
$genie->employ($resolver, My\Name\Space\Service::class, My\Name\Space\Foo::class)->invoke($target);
```

Note that `WireGenie::employ()` method does not resolve the dependencies at the moment of its call,
but at the moment of the callable invokation, once per each invokation.\
This is contrary to `WireGenie::provide*()` methods,
that resolve the dependencies at the moment of their call and only once,
regardless of how many callables are invoked by the provider returned.


### Example pseudocode

An example with in-depth code comments:
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


### Shorthand syntax

As hinted in the example above,
the instances returned by `WireGenie`'s methods are _callable_ themselves,
the following syntax may be used:
```php
// the two lines below are equivalent
$genie->provide( ... )($factoryFunction);
$genie->provide( ... )->invoke($factoryFunction);

// the two lines below are equivalent
$genie->provide( ... )(function( ... ){ ... });
$genie->provide( ... )->invoke(function( ... ){ ... });
```


## Contributing

Ideas or contribution is welcome. Please send a PR or file an issue.
