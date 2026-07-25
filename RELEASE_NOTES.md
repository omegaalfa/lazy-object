# Omegaalfa Lazy Object v1.0.0

First stable release of the small typed facade for PHP 8.4 native lazy objects.

## Highlights

- Native PHP lazy proxies through `LazyObject::proxy()`.
- Native PHP lazy ghosts through `LazyObject::ghost()`.
- Generic return-type inference for PHPStan.
- Support for `ReflectionClass::SKIP_INITIALIZATION_ON_SERIALIZE`.
- Internal `ReflectionClass` cache with no public cache-management API.
- Structural validation with complex compatibility rules delegated to PHP.
- Comprehensive PHPUnit suite, cache benchmarks and PHP 8.4 CI.
- PHP 8.4 or later and MIT license.

## Compatibility notes

The library directly uses the PHP 8.4 Reflection lazy-object API. Engine errors are propagated without broad conversion. Invalid classes, interfaces, traits, enums and abstract classes are reported as `InvalidArgumentException` by the facade.
