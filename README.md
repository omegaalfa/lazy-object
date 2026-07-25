# Lazy Object

A lightweight PHP library that leverages PHP 8.4's native lazy initialization features to defer object instantiation until actually needed.

## Requirements

- PHP 8.4 or higher

## Installation

```bash
composer require omegaalfa/lazy-object
```

## What is this for?

Sometimes you need to work with objects that are expensive to create - database connections, API clients, large data structures, etc. Loading them all upfront can slow things down, especially when you might not even use them in a particular request.

This library wraps PHP 8.4's lazy proxy and ghost object features, letting you delay object creation until the moment you actually access a property or call a method.

## Usage

### Basic Example

```php
use Omegaalfa\LazyObject\LazyObject;

// Create a lazy proxy
$lazy = new LazyObject(ExpensiveObject::class);

// Define how to build the object when needed
$object = $lazy->lazyProxy(function() {
    return new ExpensiveObject(/* heavy initialization */);
});

// Nothing happens until you actually use it
$object->someMethod(); // Now it initializes
```

### Lazy Ghost

Ghost objects work differently - they start as an empty shell and initialize themselves when first accessed:

```php
$lazy = new LazyProxyObject(MyClass::class);

$object = $lazy->lazyGhost(function($ghost) {
    // Initialize the ghost object's state
    $ghost->property = 'value';
    $ghost->loadDataFromDatabase();
});

// Still nothing loaded yet
$value = $object->property; // Initializes here
```

### Real-World Example

```php
class DatabaseConnection
{
    public function __construct(
        private string $host,
        private string $username,
        private string $password
    ) {
        // Expensive connection process
    }
}

// Wrap it
$lazy = new LazyProxyObject(DatabaseConnection::class);

$db = $lazy->lazyProxy(function() {
    return new DatabaseConnection('localhost', 'user', 'pass');
});

// Connection only happens when you run your first query
$db->query('SELECT * FROM users');
```

## Proxy vs Ghost

**Proxy**: Returns a standalone proxy object. The factory creates the real instance when needed.

**Ghost**: Returns a pre-allocated object that initializes itself on first access. The factory receives the ghost and sets its properties.

Use proxy when you want full control over instantiation. Use ghost when you need an object reference immediately but can delay its initialization.

## Running Tests

```bash
composer test
```

Or directly:

```bash
php vendor/bin/phpunit tests
```

## Static Analysis

```bash
composer phpstan
```

## License

Proprietary

## Credits

Created by wrt (webdesenvolver.agenda@gmail.com)
