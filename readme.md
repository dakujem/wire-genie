# Wire Genie 🧞

![PHP from Packagist](https://img.shields.io/packagist/php-v/dakujem/wire-genie)
[![Build Status](https://travis-ci.org/dakujem/wire-genie.svg?branch=master)](https://travis-ci.org/dakujem/wire-genie)

**Autowiring Tool & Dependency Provider** for PSR-11 service containers.
Wire with genie powers.

>
> 💿 `composer require dakujem/wire-genie`
>



## TODOs 🚧

- [x] namespace
- [x] deprecations
- [ ] docs
- [?] examples
- [x] compatibility (for annotations/wire tags)
- [x] changelog / migration guide
- [x] REJECTED split package for "providers" (provider, limiter)? (`d\Contain`, `d\Deal`, `d\Dispense`)
- [ ] TODO(s) in code
- [ ] coverage

---

## 🚧 The documentation is under construction 🚧

The rest of the docs is outdated. Feel free to experiment though, the genie has new powers! 💪

---

Allows to
- automatically detect parameter types of a _callable_
  and wire respective dependencies to invoke it
- automatically detect _constructor_ parameters of a _class_
  and wire respective dependencies to construct it
- fetch multiple dependencies from a service container
  and provide them as arguments to _callables_


## How it works

[`WireGenie`](src/EagerGenie.php) is rather simple,
it fetches specified dependencies from a container
and passes them to a callable, invoking it and returning the result.

```php
// create a Genie instance using any PSR-11 service container
$genie = new Genie($container);

$factory = function( Dependency $dep1, OtherDependency $dep2 ){
    // class MyObject ( Dependency $dep1, OtherDependency $dep2 ) { ... }
    return new MyObject($dep1, $dep2, ... );
};

// 1. invoking the factory, leaving Genie to wire the dependencies
$object = $genie->invoke($factory);

// 2. letting Genie to construct the object on its own
$object = $genie->construct(MyObject::class);

// 3. invoking the factory, explicitly specifying dependencies
$object = $genie->provide( Dependency::class, OtherDependency::class )->invoke($factory);
```

With [`WireInvoker`](src/Genie.php) it is possible to omit specifying
the dependencies and use **automatic dependency wiring**:
```php
// invoke the factory without specifying dependencies, using an automatic provider
$service = WireInvoker::employ($wireGenie)->invoke($factory);
```

`WireInvoker` will detect type-hinted parameter types or tag-hinted identifiers
at runtime and then provide dependencies to the callable.


### Note on service containers and conventions

Note that _how_ services in the container are accessed depends on the conventions used.\
Services might be accessed by plain string keys, class names or interface names.

Consider a basic key-value service container ([Sleeve](https://github.com/dakujem/sleeve)) and the different conventions:
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

There is no magic in how the services are fetched from the service container:
the two methods of [PSR-11 Container](https://www.php-fig.org/psr/psr-11/),
`ContainerInterface::get()` and `ContainerInterface::has()` are called.

However, different service containers offer different features.\
Some will seamlessly provide the option to retrieve a service by its class or interface name,
other will only return the services by the keys used for their registration.\
It is important to understand how _your_ container exposes the services to fully leverage this package.


## Basic usage

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

You now have means to allow a part of your application
on-demand access to a group of services of a certain type without injecting them all.\
This particular use-case breaks IoC if misused, though.
```php
// using $repoGenie from the previous snippet
new RepositoryConsumer($repoGenie);

// ... then inside RepositoryConsumer
$repoGenie->provide(
    'this',
    'that'
)->invoke(function(
    ThisRepository $r1,
    ThatRepository $r2
){
    // do stuff with the repos...
});
```

In use cases like the one above, it is important to limit access
to certain services only, to keep your app layers in good shape.


## Automatic dependency resolution

If you find the explicit way of `WireGenie` too verbose or insufficient,
Wire Genie package comes with the `WireInvoker` class
that enables automatic resolution of callable arguments.


### Type-hinted service identifiers

Using `WireInvoker`, it possible to omit explicitly specifying the dependencies:
```php
WireInvoker::employ($wireGenie)->invoke(function( Dependency $dep1, OtherDependency $dep2 ){
   return new Service($dep1, $dep2);
});
```

The automatic resolver will detect parameter types using type hints
and make sure that `Dependency::class` and `OtherDependency::class`
are fetched from the container.\
This works, when the services are accessible using their class names.


### Tag-hinted service identifiers

In case services are accessible by plain string identifiers
(naming conventions unrelated to the actual type-hinted class names),
or the type-hint differs from how the service is accessible,
doc-comments and "wire tags" can be used:
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
WireInvoker::employ($wireGenie)->invoke($factory);
```
In this case, services registered as `my-identifier` and `other-identifier` are fetched from the container.

> Tip 💡
>
> An empty wire tag `[wire:]` (including the colon at the end)
> can be used to indicate that a service should _not_ be wired.\
> Useful when you want to pass custom objects to a call.


### Filling in for unresolved parameters

When a callable requires passing arguments that are not resolved by the service container,
it is possible to provide them as a static argument pool:
```php
// scalars can not be resolved from the container using reflection by default
$func = function( Dependency $dep1, int $size, OtherDependency $dep2, bool $cool ){
   return $cool ? new Service($dep1, $dep2) : new OtherService($size, $dep1, $dep2);
};
// but there is a tool for that too:
WireInvoker::employ($wireGenie)->invoke($func, 42, true); // cool, right?
```
Values from the static argument pool will be used one by one to fill in for unresolvable parameters.


### When to use

Automatic argument resolution is useful for:
- async job execution
  - supplying dependencies after a job is deserialized from a queue
- method dependency injection
  - for controller methods, where dependencies differ between the handler methods
- generic factories that create instances with varying dependencies

The main purposes of the package are to provide a limited means of wiring services
without directly exposing a service container,
and to help wire services automatically at runtime.


### When not to use

Using reflection might have negative performance impact if used heavily. `WireInvoker` uses reflection to determine service types to fetch.

Fetching services from the service container on-the-fly might solve
an edge case in certain implementations where dependency injection
boilerplate can not be avoided or reduced in a different way.

Normally you want to wire your dependencies when building your app's service container.

> Disclaimer 🤚
>
> Improper use of this package might break established IoC principles
> and degrade your dependency injection container to a service locator,
> so use the package with caution.
>
> Remember, it is always better to inject a service into a working class,
> then to fetch the service from within the working class
> (this is called "Inversion of Control", "IoC").


## Integration

As with many other third-party libraries,
you should consider wrapping code using Wire Genie into a helper class
with methods like the following one
(see [`WireHelper`](examples/WireHelper.php) for full example):
```php
/**
 * Invokes a callable resolving its type-hinted arguments,
 * filling in the unresolved arguments from the static argument pool.
 * Returns the callable's return value.
 * Reading "wire" tags is enabled.
 */ 
public function wiredCall(callable $code, ...$staticArguments)
{
    return WireInvoker::employ(
        $this->wireGenie
    )->invoke($code, ...$staticArguments);
}
```

This adds a tiny layer for flexibility,
in case you decide to tweak the way you wire dependencies later on.


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


### Lazy vs. Eager service retrieval

There is a difference in the way services are fetched from the container:\
`WireInvoker` resolves the dependencies lazily
at the moment of calling its `invoke`/`construct` methods, once per each call.\
This is contrary to `WireGenie::provide*()` methods,
that resolve the dependencies at the moment of their call and only once,
regardless of how many callables are invoked by the provider returned by the methods.


## Flying carpet

We've already got a lamp and a genie... so?


## Changelog & Migration guide

>
> 📒 [Changelog](changelog.md)
>


## Testing

Run unit tests using the following command:

`$` `composer test`\
or\
`$` `php vendor/phpunit/phpunit/phpunit tests`


## Contributing

Ideas, feature requests and other contribution is welcome.
Please send a PR or create an issue.

---

Now go, do some wiring!
