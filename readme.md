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

The dox is not finished yet. Feel free to experiment though, the genie has great powers! 💪

---


Automatically wire services from a service container to **invoke callables** or **construct objects**.

```php
$container = new Any\Psr11\Container([
    Thing::class => new Thing(),
    MyService::class => new MyService(),
]);

$callable = function (MyService $service, Thing $thing){ ... };

class Something {
    public function __construct(MyService $service, Thing $thing) { ... }
}

$g = new Dakujem\Wire\Genie($container);

// Magic! The arguments are resolved from the container.
$value  = $g->invoke($callable);
$object = $g->construct(Something::class);
```

That is only the basis, the process is customizable and more powerful.

For each parameter it is possible to:
- override type-hint and wire an explicit dependency (override the type-hint)
- construct missing services on demand
- skip wiring (treat as unresolvable)
- override value (bypass the container)

```php
// override type-hint(s)
$callable = function (#[Wire(MyService::class)] ServiceInterface $service, #[Wire(Thing::class)] $thing){ ... };
$value  = $g->invoke($callable);

// construct object(s) if not present in the container
$callable = function (#[Hot] Something $some, #[Make(Thing::class)] $thing){ ... };
$value  = $g->invoke($callable);

// provide arguments for scalar-type, no-type and otherwise unresolvable parameters
$callable = function (string $question, MyService $service, int $answer){ ... };
$g->invoke($callable, 'The Ultimate Question of Life, the Universe, and Everything.', 42);
$g->invoke($callable, answer: 42, question: 'The Ultimate Question of Life, the Universe, and Everything.',);

// skip wiring for a parameter
$callable = function (#[Skip] MyService $service){ ... };
$g->invoke($callable, new MyService(...)); // provide your own argument(s)
```


## How it works

There are two primary methods:
```php
Genie::invoke(  callable $target, ...$pool );
Genie::construct( string $target, ...$pool );
```
... where the variadic `$pool` is a list of values that will be used for unresolvable parameters.

The resolution algorithm works like the following. If any step succeeds, the rest is skipped.\
For each parameter...
1. If the parameter name matches a _named argument_ from the pool, use it.
2. If `#[Skip]` hint is present, skip steps 3-6 and treat the parameter as unresolvable.
3. If a `#[Wire(Identifier::class)]` hint (attribute) is present, resolve the hinted identifier using the container.
4. Resolve the type-hinted identifier using the container.
5. If `#[Hot]` hint is present, try to create the type-hinted class.
6. If `#[Make(Name::class)]` hint is present, create the hinted class.
7. When a parameter is unresolvable, try filling in an argument from the pool.
8. When a default parameter value is present, use it.
9. If the parameter is nullable, use `null`.
10. Fail utterly.


## What can it be used for?

- middleware / pipeline _dispatchers_
- asynchronous _job execution_
  - supplying dependencies after a job is deserialized from a queue
- generic _factories_ that create instances with varying dependencies
- _method dependency injection_
  - for controllers, where dependencies are wired at runtime


**🚧🚧🚧 TODO real example: job dispatcher**

**🚧🚧🚧 TODO real example: controller method injector**


### A word of caution

Fetching services from the service container on-the-fly might solve
an edge case in certain implementations where dependency injection
boilerplate can not be avoided or reduced in a different way.

It is also the only way to invoke callables with dependencies not known at the time of compilation.

Normally, however, you want to wire your dependencies when building your app's service container.

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
with methods like the following one:
```php
/**
 * Invokes a callable resolving its type-hinted arguments,
 * filling in the unresolved arguments from the static argument pool.
 * Returns the callable's return value.
 * Also allows to create objects passing in a class name.
 */ 
public function call(callable|string $target, ...$pool): mixed
{
    return Genie::employ($this->container)($target, ...$pool);
}
```

This adds a tiny layer for flexibility,
in case you decide to tweak the way you wire dependencies later on.


## Static provisioning

`Genie::provide()` can be used to provision a callable with a fixed list of services without using reflection.

```php
$factory = function( Dependency $dep1, OtherDependency $dep2 ): MyObject {
    return new MyObject($dep1, $dep2);
};
$object = $g->provide( Dependency::class, OtherDependency::class )->invoke($factory);
```


## Limiting access to services

You can limit the services accessible through `Genie` by using a filtering proxy `Limiter`:
```php
$repoGenie = new Genie(
    new Dakujem\Wire\Limiter($container, [
        RepositoryInterface::class,
        // you may whitelist multiple classes or interfaces
    ])
);
```
The proxy uses the `instanceof` type operator
and throws if the requested service does not match at least one of the whitelisted classes or interface names.


## Customization

A custom _strategy_ can be inserted into `Genie`,
and the default `AttributeBasedStrategy` allows for customization of the resolver mechanism,
thus providing ultimate configurability.


## Compatibility

Any PSR-11 container can be used.


## Wonderful lamp

```php
// If we happen to find a magical lamp...
$lamp = new Dakujem\Wire\Lamp($container);

// we can rub it, and a genie might come out!
$genie = $lamp->rub();

// My wish number one is...
$genie->construct(Palace::class);
```


## Flying carpet

We've already got a lamp and a genie... so?


## Installation

>
> 💿 `composer require dakujem/wire-genie`
>
>
> 📒 [Changelog & Migration guide](changelog.md)
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
