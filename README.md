# smolSession

An opinionated PHP session management library designed to expose a simple API while providing smart performance optimizations and minimizing session creation and locking.

## Features

- **Lazy session creation** - Sessions are only started when data is actually written, avoiding unnecessary cookie traffic
- **Minimized locking** - Sessions are only locked once on first read, and again if necessary during commits, not for the entire response lifecycle
- **Atomic updates** - Operations like increments that apply atomically on commit are possible
- **Simple static API** - Clean, straightforward interface for common session operations

## Installation

```bash
composer require joby/smol-session
```

## Usage

```php
use Joby\Smol\Session\Session;

// Instantiate a single object
// More than one may exist, and they will each store their own queue of atomic edits
$session = new Session();

// Set values (queued, doesn't lock the session)
$session->set('user_id', 123);
$session->set('username', 'john_doe');

// Increment a counter (queued, doesn't lock the session, will apply to actual value upon commit to avoid race conditions)
$session->increment('page_views');
$session->increment('score', 10);

// Unset values (queued, doesn't lock the session)
$session->unset('temp_data');

// Read values (applies queued updates to cached values for convenience)
$userId = $session->get('user_id');
$views = $session->get('page_views');

// Commit all changes at once atomically, does not reopen session if no changes are queued
$session->commit();

// Can rotate session IDs
$session->rotate();

// Can also destroy the session, deleting all data and unsetting the cookie
$session->destroy();

```

## Typed Getters

smolSession provides type-safe getter methods that automatically convert session values to the expected type:
```php
// Type-safe access with automatic conversion
$userId = $session->getInt('user_id');       // Converts "123" → 123
$price = $session->getFloat('cart_total');   // Converts "19.99" → 19.99
$enabled = $session->getBool('dark_mode');   // Converts "yes" → true
$username = $session->getString('username'); // Converts 123 → "123"

// With defaults for missing values
$limit = $session->getInt('page_limit', 20);
$theme = $session->getString('theme', 'default');
$debug = $session->getBool('debug', false);

// Require values (throw TypeCastException if null/missing)
$requiredId = $session->requireInt('user_id');
$requiredEmail = $session->requireString('email');
```

### Type Conversion Rules

Values are converted pretty permissively using (https://github.com/joby-lol/smol-cast)[smolCast], see its readme for detailed rules about what can be converted and how it works.

## Static facade

There is also a static facade that stores its own internal singleton and exposes the same interface as an object would, see `Joby\Smol\Session\SessionFacade`.

## How It Works

1. **Reading** - `$session->get()` reads from a cached copy of the session and applies any queued updates
2. **Writing** - `$session->set()`, `$session->increment()`, and `$session->unset()` queue changes without opening the session
3. **Committing** - `$session->commit()` opens the session, applies all updates atomically, and closes it

This approach minimizes session file locking and reduces the window where concurrent requests might conflict.

## Advanced: Custom Atomic Updates

You can create custom atomic update operations by implementing the `SessionUpdate` interface:

```php
use Joby\Smol\Session\SessionUpdate;

class AppendToArray implements SessionUpdate
{
    public function __construct(public mixed $value) {}

    public function apply(mixed $current_value): array
    {
        $array = is_array($current_value) ? $current_value : [];
        $array[] = $this->value;
        return $array;
    }

    public function isAbsolute(): bool
    {
        return false; // depends on current value
    }
}

// Use with $session->update()
$session->update('items', new AppendToArray('new_item'));
```

The `isAbsolute()` method indicates whether the update replaces the value entirely (like `SetValue`) or depends on the current value (like `IncrementValue`). Absolute updates enable performance optimizations by discarding previous queued updates for the same key.

## Requirements

Fully tested on PHP 8.3+, static analysis for PHP 8.1.

## License

MIT License - See [LICENSE](LICENSE) file for details.