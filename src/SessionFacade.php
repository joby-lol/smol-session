<?php

/**
 * smolSession
 * https://github.com/joby-lol/smol-session
 * (c) 2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Session;

use Stringable;
use Throwable;

/**
 * Static facade for interacting with PHP's built-in session management. Can be used for simple getting and setting, but provides so much more than that. This interface also:
 * 
 * - Lazily starts sessions only when data is written, to avoid uneccessary session data and cookie traffic.
 * - Only opens the session again for writing when commit() is called, and even then only for changes.
 * - Allows complex atomic updates to data, such as incrementing values.
 * 
 * @codeCoverageIgnore this is really just a facade
 */
class SessionFacade
{

    protected static Session $session;

    public static function session(): Session
    {
        return static::$session
            ??= new Session();
    }

    public static function setSession(Session $session): void
    {
        static::$session = $session;
    }

    /**
     * Get the value for a given key, applying any queued updates.
     */
    public static function get(string $key): mixed
    {
        return static::session()->get($key);
    }

    /**
     * Set the value for a given key, queuing a SetValue update.
     */
    public static function set(string $key, mixed $value): void
    {
        static::session()->set($key, $value);
    }

    /**
     * Set the value for a given key if it is currently null, queuing a SetIfNullValue update.
     */
    public static function setIfNull(string $key, mixed $value): void
    {
        static::session()->setIfNull($key, $value);
    }

    /**
     * Unset the value for a given key, queuing an UnsetValue update
     */
    public static function unset(string $key): void
    {
        static::session()->unset($key);
    }

    /**
     * Increment the value for a given key by a given amount, queuing an IncrementValue update.
     */
    public static function increment(string $key, int|float $by = 1): void
    {
        static::session()->increment($key, $by);
    }

    /**
     * Toggle the boolean value for a given key, queuing a ToggleValue update.
     */
    public static function toggle(string $key): void
    {
        static::session()->toggle($key);
    }

    /**
     * Touch the value for a given key, queuing a TouchValue update. This will set the value to the current timestamp if it is not numeric, or update it to the current timestamp (or later if it's already set further out than time()).
     */
    public static function touch(string $key): void
    {
        static::session()->touch($key);
    }

    /**
     * Destroy the entire session, including all managed data. This will immediately discard all cached data and queued updates, clear the session cookie, and remove all existing session data from the server.
     * 
     * Does nothing but clear internal state if there is no existing session.
     */
    public static function destroy(): void
    {
        static::session()->destroy();
    }

    /**
     * Rotate the session ID, generating a new one while keeping all data intact. Deletes the old session ID unless $keep_old_session is true. This creates a more secure session rotation by default, potentially at the expense of usability in some cases. If your app makes many concurrent requests that share a session, you may want to set $keep_old_session to true to avoid session loss in those requests.
     * 
     * Does nothing if there is no existing session.
     */
    public static function rotate(bool $keep_old_session = false): void
    {
        static::session()->rotate($keep_old_session);
    }

    /**
     * Return whether any session values have been read, or whether a specific one has by passing a key.
     */
    public static function read(string|null $key = null): bool
    {
        return static::session()->read($key);
    }

    /**
     * Return whether any session values are queued to be written, or whether a specific one is by passing a key.
     */
    public static function written(string|null $key = null): bool
    {
        return static::session()->written($key);
    }

    /**
     * Queue an update to be applied to a given session key on commit.
     */
    public static function update(string $key, SessionUpdate $update): void
    {
        static::session()->update($key, $update);
    }

    /**
     * Commit any queued updates to the session storage. This will start the session if it has not already been started, and write and close it when done. If no updates are queued, this is a no-op. Can be called multiple times per request if desired, as it clears internal state after running.
     */
    public static function commit(): void
    {
        static::session()->commit();
    }

    /**
     * Mark a key as having been read.
     */
    public static function markRead(string $key): void
    {
        static::session()->markRead($key);
    }

    /**
     * Change the storage key (effectively a namespace) in which managed data will actually be stored in $_SESSION. Changing this resets the entire class's state including any uncommitted changes.
     */
    public static function setStorageKey(string $key): void
    {
        static::session()->setStorageKey($key);
    }

    /**
     * Get the current storage key (effectively a namespace) in which managed data is stored in $_SESSION.
     */
    public static function storageKey(): string
    {
        return static::session()->storageKey();
    }

    /**
     * Return whether a session currently exists.
     */
    public static function exists(): bool
    {
        return static::session()->exists();
    }

    /**
     * Get the int value of the given property name, or the provided default if the raw value is null.
     * 
     * @throws Throwable if the value cannot be cast to an int.
     * 
     * @return ($default is null ? int|null : int)
     */
    public static function getInt(string|Stringable $key, int|null $default = null): int|null
    {
        return static::session()->getInt($key, $default);
    }

    /**
     * Require the int value of the given property name, throwing if the raw value is null.
     * 
     * @throws Throwable if the value is null or cannot be cast to an int.
     */
    public static function requireInt(string|Stringable $key): int
    {
        return static::session()->requireInt($key);
    }

    /**
     * Get the float value of the given property name, or the provided default if the raw value is null.
     * 
     * @throws Throwable if the value cannot be cast to a float.
     * 
     * @return ($default is null ? float|null : float)
     */
    public static function getFloat(string|Stringable $key, float|null $default = null): float|null
    {
        return static::session()->getFloat($key, $default);
    }

    /**
     * Require the float value of the given property name, throwing a TypeCastException if the raw value is null.
     * 
     * @throws Throwable if the value is null or cannot be cast to a float.
     */
    public static function requireFloat(string|Stringable $key): float
    {
        return static::session()->requireFloat($key);
    }

    /**
     * Get the bool value of the given property name, or the provided default if the raw value is null.
     * 
     * @throws Throwable if the value cannot be cast to a bool.
     * 
     * @return ($default is null ? bool|null : bool)
     */
    public static function getBool(string|Stringable $key, bool|null $default = null): bool|null
    {
        return static::session()->getBool($key, $default);
    }

    /**
     * Require the bool value of the given property name, throwing a TypeCastException if the raw value is null.
     * 
     * @throws Throwable if the value is null or cannot be cast to a bool.
     */
    public static function requireBool(string|Stringable $key): bool
    {
        return static::session()->requireBool($key);
    }

    /**
     * Require the string value of the given property name, throwing a TypeCastException if the raw value is null.
     * 
     * @throws Throwable if the value is null or cannot be cast to a string.
     * 
     * @return ($default is null ? string|null : string)
     */
    public static function getString(string|Stringable $key, string|Stringable|null $default = null): string|null
    {
        return static::session()->getString($key, $default);
    }

    /**
     * Require the string value of the given property name, throwing a TypeCastException if the raw value is null.
     * 
     * @throws Throwable if the value is null or cannot be cast to a string.
     */
    public static function requireString(string|Stringable $key): string
    {
        return static::session()->requireString($key);
    }

}
