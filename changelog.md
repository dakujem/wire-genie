# Wire Genie 🧞

| Wire Genie branch | Supported PHP versions |
|:------------------|:-----------------------|
| `1.x` | 7.2, 7.3, 7.4, 8.0 |
| `2.x` | 7.2, 7.3, 7.4, 8.0+ |
| `3.x` | 8.0+ (with native attributes support) |

## Changelog


### v3 (PHP 8 only)

This version requires PHP 8+.

The compatibility layer present in v2 was removed.\
All deprecated classes and methods have been removed.

The default strategy for argument type detection is now based on _native attributes_,
but is very close to the one based on annotations (in comments) function-wise.


### Migrating to v3 from v2

Update your code to use _native attributes_ instead of the _wire tag_.

| Old code using _wire tags_ | Replace with |
|:---------|:-------------|
| `@param $foo [wire:identifier]` | `#[Wire('identifier')]` attribute |
| `@param $foo [wire:App\Services\FooService]` (class-name wire tag) | `#[Wire(App\Services\FooService::class)]` attribute |
| `@param $foo [wire:]` (empty wire tag) | `#[Skip]` attribute |

These attributes need to be applied on the particular parameter:
```php
function(#[Wire('identifier')] $service1, #[Skip] $service2) { ... }
```


### v2

Breaking changes:
- methods `WireGenie::provideSafe` and `WireGenie::provideStrict` removed
- renamed classes and namespace altered (added one level of nesting)
- `WireInvoker` and `WireGenie` have been merged to the `Genie` class, which now assumed the functionality of both

Renamed classes

| Previous | Current |
|:---------|:--------|
| `Dakujem\WireGenie` | `Dakujem\Wire\Genie` * |
| `Dakujem\WireInvoker` | `Dakujem\Wire\Genie` * |
| `Dakujem\InvokableProvider` | `Dakujem\Wire\Simpleton` |
| `Dakujem\WireLimiter` | `Dakujem\Wire\Limiter` |
| `Dakujem\WireLimiterException` | `Dakujem\Wire\Exceptions\ServiceNotWhitelisted` |
| `Dakujem\PredictableAccess` | `Dakujem\Wire\PredictableAccess` |
| `Dakujem\Invoker` (interface) | `Dakujem\Wire\Invoker` |
| `Dakujem\Constructor` (interface) | `Dakujem\Wire\Constructor` |

Compatibility layer:
- renamed classes and interfaces have aliases registered, 95% of the old code should still work
- deprecated classes: `Dakujem\ArgInspector`, `Dakujem\WireLimiterException`

Runtime exceptions are now based on `Dakujem\Wire\Exceptions\Unresolvable` exception interface. Any configuration error and incorrect usage will result in a `LogicException`.


### Migrating to v2 from v1

Needed:
1. Replace any usage of `WireGenie::provideSafe` with `Genie::provide`.
2. Replace any usage of `WireGenie::provideStrict` with `Genie::provide`, remove "nullability" of the code being invoked where applicable.

Recommended:
1. Update the namespaces / `use` statements of all classes in use, see the "Renamed classes" table above.

