# Wire Genie - Dependency Provider

[![Build Status](https://travis-ci.org/dakujem/wire-genie.svg?branch=master)](https://travis-ci.org/dakujem/wire-genie)


> 💿 `composer require dakujem/wire-genie`

Allows to fetch multiple dependencies from a service container
and provide them as arguments to callables.\
Also allows to automatically detect parameter types and wire respective
dependencies to invoke callables or construct class instances.

> Disclaimer 🤚
>
> Improper use of this package might break established IoC principles
> and degrade your dependency injection container to a service locator,
> so use the package with caution.


The main purposes of the package are to provide a limited means of wiring services
without directly exposing a service container,
and to help wire services automatically.

> 💡
>
> This approach solves an edge case in certain implementations where dependency injection
> boilerplate can not be avoided or reduced in a different way.
>
> Normally you want to wire your dependencies when building your app's service container.


## How it works

[`WireGenie`](src/WireGenie.php) is rather simple,
it fetches specified dependencies from a container
and passes them to a callable, invoking it and returning the result.

```php
$wireGenie = new WireGenie($container);

$factory = function( Dependency $dep1, OtherDependency $dep2, ... ){
    // create stuff...
    return new Service($dep1, $dep2, ... );
};

// create a provider, explicitly specifying dependencies
$provider = $wireGenie->provide( Dependency::class, OtherDependency::class, ... );

// invoke the factory using the provider
$service = $provider->invoke($factory);
```

With [`WireInvoker`](src/WireInvoker.php) it is even possible to omit declaring the dependencies:

```php
// invoke the factory without specifying dependencies, using an automatic provider
$service = WireInvoker::employ($wireGenie)->invoke($factory);
```


### Note on service containers and conventions

Note that _how_ services in the container are accessed depends on the conventions used.\
Services might be accessed by plain string keys, class names or interface names.

Wire Genie simply calls methods of [PSR-11 Container](https://www.php-fig.org/psr/psr-11/)
`ContainerInterface::get()` and `ContainerInterface::has()` under the hood,
there is no other "magic".

Consider a service container ([Sleeve](https://github.com/dakujem/sleeve)) and the different conventions:
```php
$sleeve = new Sleeve();
// using a plain string identifier
$sleeve->set('genie', function (Sleeve $container) {
    return new WireGenie($container);
});
// using a class name identifier
$sleeve->set(WireGenie::class, function (Sleeve $container) {
    return new WireGenie($container);
});

// using a plain string identifier
$sleeve->set('self', $sleeve);
// using an interface name identifier
$sleeve->set(ContainerInterface::class, $sleeve);
```

The services can be accessed by calling either
```php
$sleeve->get('genie');
$sleeve->get(WireGenie::class);

$sleeve->get('self');
$sleeve->get(ContainerInterface::class);
```

Different service containers expose services differently.
Some offer both conventions, some offer only one.\
It is important to understand how _your_ container exposes the services to fully leverage `WireGenie` and `WireInvoker`.


## Usage

> Note: In the following example, services are accessed using plain string keys.

```php
// Use any PSR-11 compatible container you like.
$container = AppContainerPopulator::populate(new Sleeve());

// Give Wire Genie full access to your service container,
$genie = new WireGenie($serviceContainer);

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

You now have means to allow a service
on-demand access to other services of a certain type without injecting them all.\
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
> ✳ the actual keys depend on the conventions of exposing services by the container.

In use cases like the one above, it is important to limit access
to certain services only, to keep your app layers in good shape.


## Automatic dependency resolution

If you find the explicit way of `WireGenie` too verbose or insufficient,
Wire Genie package also comes with a `WireInvoker` class
that enables automatic resolution of callable arguments.

It is then possible to omit explicitly specifying the dependencies:
```php
WireInvoker::employ($wireGenie)->invoke(function( Dependency $dep1, OtherDependency $dep2 ){
   return new Service($dep1, $dep2);
});
```

The automatic resolver will make sure that `Dependency::class` and `OtherDependency::class`
are fetched from the container,
provided the services are accessible using their class names.

In case services are accessible by plain string identifiers, doc-comments and "tags" can be used:
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
// note that we are using a custom detector with tag reader this time:
WireInvoker::employ($wireGenie, ArgInspector::typeDetector(ArgInspector::tagReader()))->invoke($factory);
```
In this case, services registered as `my-identifier` and `other-identifier` are fetched from the container.

Note that `WireInvoker` resolves the dependencies at the moment of calling its `invoke`/`construct` methods, once per each call.\
This is contrary to `WireGenie::provide*()` methods,
that resolve the dependencies at the moment of their call and only once,
regardless of how many callables are invoked by the provider returned by the methods.

You might consider implementing an invoker helper class with a method like the following (see [`WireHelper`](examples/WireHelper.php) for full example):
```php
/**
 * Invokes a callable resolving its type-hinted arguments,
 * filling in the unresolved arguments from the static argument pool.
 * Returns the callable's return value.
 * Using "wire" tags is enabled.
 */ 
public function wiredCall(callable $code, ...$staticArguments)
{
    return WireInvoker::employ(
        $this->wireGenie,
        ArgInspector::typeDetector(ArgInspector::tagReader())
    )->invoke($code, ...$staticArguments);
}
```

Automatic argument resolution is useful for:
- async job execution
    - supplying dependencies after a job is deserialized from a queue
- method dependency injection
    - for controller methods, where dependencies differ between the handler methods
- generic factories that create instances with varying dependencies

> Note that using reflection might have negative performance impact
> if used heavily.


## Advanced

### Implementing custom logic around `WireInvoker`'s core

It is possible to configure every aspect of `WireInvoker`.\
Pass callables to its constructor to configure
how services are wired to invoked callables or created instances.

For exmaple, if every service was accessed by its class name,
except the backslashes `\` were replaced by dots '.' and in lower case,
you could implement the following to invoke `$target` callable:
```php
$proxy = function(string $identifier, ContainerInterface $container) {
    $key = str_replace('\\', '.', strtolower($identifier)); // alter the service key
    return $container->has($key) ? $container->get($key) : null;
};
new WireInvoker(null, $proxy); // using custom proxy
```


### Example pseudocode for `WireGenie`

An example with in-depth code comments:
```php
// Given a factory function like the following one:
$factoryFunction = function( /*...dependencies...*/ ){
    // do stuff or create stuff
    return new Service( /*...dependencies...*/ );
};

// Give access to full service container
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
the provider instances returned by `WireGenie`'s methods are _callable_ themselves,
the following syntax may be used:
```php
// the two lines below are equivalent
$genie->provide( ... )($factoryFunction);
$genie->provide( ... )->invoke($factoryFunction);

// the two lines below are equivalent
$genie->provide( ... )(function( ... ){ ... });
$genie->provide( ... )->invoke(function( ... ){ ... });
```

The shorthand syntax may also be used with `WireInvoker`, which itself is _callable_.


## Contributing

Ideas or contribution is welcome. Please send a PR or file an issue.
