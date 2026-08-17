<?php

/**
 * smolSession
 * https://github.com/joby-lol/smol-session
 * (c) 2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Session;

use Joby\Smol\Cast\CastingGettersTrait;
use Joby\Smol\Cast\TypeCastException;
use RuntimeException;
use Throwable;

/**
 * Class for interacting with PHP's built-in session management. Can be used for simple getting and setting, but provides much more than that. This interface also:
 * 
 * - Lazily starts sessions only when data is written, to avoid uneccessary session data and cookie traffic.
 * - Only opens the session again for writing when commit() is called, and even then only for changes.
 * - Allows complex atomic updates to data, such as incrementing values.
 */
class Session
{

    use CastingGettersTrait;

    protected SystemSessionHelper $system;

    /** 
     * @var array<string,SessionUpdate[]> $updates a list of queued updates to apply on commit
     */
    protected array $updates = [];

    /**
     * @var array<string> $was_read a list of session keys that have been read during this request
     */
    protected array $was_read = [];

    /**
     * @var array<mixed>|null $data the session data, cached from $this->system->data on first read
     */
    protected array|null $data = null;

    /**
     * @var string $storage_key the key in $this->system->data where managed session data is stored. If modified, the entire class will be reset and all uncommitted changes lost.
     */
    protected string $storage_key = '_smol_session_data';

    public function __construct(
        SystemSessionHelper|null $system = null,
    )
    {
        $this->system = $system ?? new SystemSessionHelper();
    }

    /**
     * Get the value for a given key, applying any queued updates.
     */
    public function get(string $key): mixed
    {
        $this->loadData();
        $this->markRead($key);
        $value = $this->data[$key] ?? null;
        return $this->applyUpdates($key, $value);
    }

    /**
     * Set the value for a given key, queuing a SetValue update.
     */
    public function set(string $key, mixed $value): void
    {
        $this->update($key, new SetValue($value));
    }

    /**
     * Set the value for a given key if it is currently null, queuing a SetIfNullValue update.
     */
    public function setIfNull(string $key, mixed $value): void
    {
        $this->update($key, new SetIfNullValue($value));
    }

    /**
     * Unset the value for a given key, queuing an UnsetValue update
     */
    public function unset(string $key): void
    {
        if ($this->get($key) === null)
            return;
        $this->update($key, new UnsetValue());
    }

    /**
     * Increment the value for a given key by a given amount, queuing an IncrementValue update.
     */
    public function increment(string $key, int|float $by = 1): void
    {
        $this->update($key, new IncrementValue($by));
    }

    /**
     * Toggle the boolean value for a given key, queuing a ToggleValue update.
     */
    public function toggle(string $key): void
    {
        $this->update($key, new ToggleValue());
    }

    /**
     * Touch the value for a given key, queuing a TouchValue update. This will set the value to the current timestamp if it is not numeric, or update it to the current timestamp (or later if it's already set further out than time()).
     */
    public function touch(string $key): void
    {
        $this->update($key, new TouchValue());
    }

    /**
     * Destroy the entire session, including all managed data. This will immediately discard all cached data and queued updates, clear the session cookie, and remove all existing session data from the server.
     * 
     * Does nothing but clear internal state if there is no existing session.
     */
    public function destroy(): void
    {
        // clear internal state
        $this->updates = [];
        $this->was_read = [];
        $this->data = null;
        // if there is no session then we're done
        if ($this->system->session_status() === PHP_SESSION_NONE && !isset($_COOKIE[$this->system->session_name()])) {
            return;
        }
        // otherwise, open and destroy the session
        $this->system->session_start();
        // clear the session cookie
        $params = $this->system->session_get_cookie_params();
        $this->system->setcookie(
            $this->system->session_name(), // @phpstan-ignore-line when not setting, this always returns a string
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly'],
        );
        // destroy the PHP session
        $this->system->session_destroy();
    }

    /**
     * Rotate the session ID, generating a new one while keeping all data intact. Deletes the old session ID unless $keep_old_session is true. This creates a more secure session rotation by default, potentially at the expense of usability in some cases. If your app makes many concurrent requests that share a session, you may want to set $keep_old_session to true to avoid session loss in those requests.
     * 
     * Does nothing if there is no existing session.
     */
    public function rotate(bool $keep_old_session = false): void
    {
        if ($this->system->session_status() === PHP_SESSION_NONE && !isset($_COOKIE[$this->system->session_name()])) {
            return;
        }
        // commit before rotation to ensure all changes are saved
        $this->commit();
        // open and rotate the session
        $this->system->session_start();
        $this->system->session_regenerate_id(!$keep_old_session);
        $this->system->session_write_close();
    }

    /**
     * Apply any queued updates for the named key to a given value.
     */
    protected function applyUpdates(string $key, mixed $value): mixed
    {
        if (!isset($this->updates[$key])) {
            return $value;
        }
        foreach ($this->updates[$key] as $update) {
            $value = $update->apply($value);
        }
        return $value;
    }

    /**
     * Return whether any session values have been read, or whether a specific one has by passing a key.
     */
    public function read(string|null $key = null): bool
    {
        if ($key === null) {
            return !empty($this->was_read);
        }
        return in_array($key, $this->was_read, true);
    }

    /**
     * Return whether any session values are queued to be written, or whether a specific one is by passing a key.
     */
    public function written(string|null $key = null): bool
    {
        if ($key === null) {
            return !empty($this->updates);
        }
        return isset($this->updates[$key]);
    }

    /**
     * Queue an update to be applied to a given session key on commit.
     */
    public function update(string $key, SessionUpdate $update): void
    {
        if ($update->isAbsolute()) {
            // absolute updates clear any prior updates as a performance optimization
            $this->updates[$key] = [$update];
            return;
        }
        // otherwise, just append the update
        $this->updates[$key][] = $update;
    }

    /**
     * Commit any queued updates to the session storage. This will start the session if it has not already been started, and write and close it when done. If no updates are queued, this is a no-op. Can be called multiple times per request if desired, as it clears internal state after running.
     */
    public function commit(): void
    {
        if (!$this->written()) {
            return;
        }
        // we start our own session here rather than relying on session_start() having been called earlier, because we may not have needed to read any data during this request, and we want to modify the actual current values of the session, not what was cached earlier
        $this->system->session_start();
        $new_values = [];
        $unset_keys = [];
        $storage_key = $this->storageKey();
        if (!isset($this->system->data[$storage_key])) {
            $this->system->data[$storage_key] = [];
        }
        if (!is_array($this->system->data[$storage_key])) {
            // if the storage key is not an array, we can't proceed
            throw new RuntimeException('Session storage key "' . $storage_key . '" is not an array.');
        }
        foreach (array_keys($this->updates) as $key) {
            $value = $this->system->data[$storage_key][$key] ?? null;
            $value = $this->applyUpdates($key, $value);
            if ($value === null) {
                $unset_keys[] = $key;
            }
            else {
                $new_values[$key] = $value;
            }
        }
        // apply new values after they are all built, so that if an exception occurs we don't leave the session in a half-updated state
        foreach ($new_values as $key => $value) {
            $this->system->data[$storage_key][$key] = $value;
        }
        // now unset any keys that need to be removed
        foreach ($unset_keys as $key) {
            // @phpstan-ignore-next-line because we have already checked that this is an array
            unset($this->system->data[$storage_key][$key]);
        }
        // write and close the session
        $this->system->session_write_close();
        // clear internal state so that commit can be called multiple times per request if desired
        $this->updates = [];
        $this->was_read = [];
        $this->data = null;
    }

    /**
     * Mark a key as having been read.
     */
    public function markRead(string $key): void
    {
        if (!in_array($key, $this->was_read, true)) {
            $this->was_read[] = $key;
        }
    }

    /**
     * Change the storage key (effectively a namespace) in which managed data will actually be stored in $this->system->data. Changing this resets the entire class's state including any uncommitted changes.
     */
    public function setStorageKey(string $key): void
    {
        if ($key === $this->storage_key)
            return;
        $this->storage_key = $key;
        $this->updates = [];
        $this->was_read = [];
        $this->data = null;
    }

    /**
     * Get the current storage key (effectively a namespace) in which managed data is stored in $this->system->data.
     */
    public function storageKey(): string
    {
        return $this->storage_key;
    }

    /**
     * Return whether a session currently exists.
     */
    public function exists(): bool
    {
        return $this->system->session_status() !== PHP_SESSION_NONE
            || isset($_COOKIE[$this->system->session_name()])
            || isset($this->system->data[$this->storageKey()]);
    }

    protected function loadData(bool $force_refresh = false): void
    {
        // if we have data, and we're not forcing a refresh, then we're done
        if ($this->data !== null && !$force_refresh)
            return;
        // if there is no session then we're done
        if (!$this->exists()) {
            $this->data = [];
            return;
        }
        // otherwise, load the session data
        $this->system->session_start();
        $storage_key = $this->storageKey();
        if (!isset($this->system->data[$storage_key])) {
            $this->system->data[$storage_key] = [];
        }
        if (!is_array($this->system->data[$storage_key])) {
            // if the storage key is not an array, we can't proceed
            throw new RuntimeException('Session storage key "' . $storage_key . '" is not an array.');
        }
        $this->data = $this->system->data[$storage_key];
        $this->system->session_abort();
    }

    /**
     * @inheritDoc
     * 
     * This implementation of CastingGettersTrait::getCastableValue() loads session data for casting.
     */
    protected function getCastableValue(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * @inheritDoc
     */
    protected function createCastException(string $type, string $name, Throwable $previous): Throwable
    {
        return new TypeCastException("Could not cast session item $name to $type", previous: $previous);
    }

    /**
     * @inheritDoc
     */
    protected function createRequiredException(string $type, string $name): Throwable
    {
        return new TypeCastException("Required session item $name was not found");
    }

}
