<?php

/**
 * smolSession
 * https://github.com/joby-lol/smol-session
 * (c) 2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Session\Tests\Integration;

use Joby\Smol\Cast\TypeCastException;
use Joby\Smol\Session\Session;
use Joby\Smol\Session\SessionFacade;
use PHPUnit\Framework\TestCase;

class SessionFacadeTest extends TestCase
{

    protected function setUp(): void
    {
        // Clean up any existing session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Configure session for testing
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.cache_limiter', '');

        // Clear any existing session data
        $_SESSION = [];

        // Reset Session class state
        $this->resetSessionState();
    }

    protected function tearDown(): void
    {
        // Clean up session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];

        // Reset Session class state
        $this->resetSessionState();
    }

    protected function resetSessionState(): void
    {
        SessionFacade::setSession(new Session());
    }

    public function test_set_queues_value()
    {
        SessionFacade::set('foo', 'bar');
        $this->assertTrue(SessionFacade::written('foo'));
    }

    public function test_get_returns_queued_value()
    {
        SessionFacade::set('foo', 'bar');
        $this->assertEquals('bar', SessionFacade::get('foo'));
    }

    public function test_get_returns_null_for_nonexistent_key()
    {
        $this->assertNull(SessionFacade::get('nonexistent'));
    }

    public function test_increment_queues_update()
    {
        SessionFacade::increment('counter');
        $this->assertTrue(SessionFacade::written('counter'));
    }

    public function test_increment_returns_correct_value()
    {
        SessionFacade::increment('counter');
        $this->assertEquals(1, SessionFacade::get('counter'));
    }

    public function test_multiple_increments_apply_correctly()
    {
        SessionFacade::increment('counter', 5);
        SessionFacade::increment('counter', 3);
        SessionFacade::increment('counter', 2);

        $this->assertEquals(10, SessionFacade::get('counter'));
    }

    public function test_unset_queues_update_when_data_exists()
    {
        SessionFacade::set('temp', 'data');
        SessionFacade::unset('temp');
        $this->assertTrue(SessionFacade::written('temp'));
    }

    public function test_unset_does_not_queue_update_when_data_does_not_exist()
    {
        SessionFacade::unset('temp');
        $this->assertFalse(SessionFacade::written('temp'));
    }

    public function test_unset_returns_null()
    {
        SessionFacade::set('temp', 'data');
        SessionFacade::unset('temp');
        $this->assertNull(SessionFacade::get('temp'));
    }

    public function test_commit_persists_unset()
    {
        SessionFacade::set('temp', 'value');
        SessionFacade::commit();

        SessionFacade::unset('temp');
        SessionFacade::commit();

        // Reset internal cache
        $this->resetSessionState();

        $this->assertNull(SessionFacade::get('temp'));
    }

    public function test_commit_without_changes_is_noop()
    {
        $sessionActive = session_status() === PHP_SESSION_ACTIVE;
        SessionFacade::commit();

        // Session should not have been started
        $this->assertEquals($sessionActive, session_status() === PHP_SESSION_ACTIVE);
    }

    public function test_written_returns_false_when_no_updates()
    {
        $this->assertFalse(SessionFacade::written());
    }

    public function test_written_returns_true_when_updates_exist()
    {
        SessionFacade::set('foo', 'bar');
        $this->assertTrue(SessionFacade::written());
    }

    public function test_written_with_key_returns_correct_value()
    {
        SessionFacade::set('foo', 'bar');
        $this->assertTrue(SessionFacade::written('foo'));
        $this->assertFalse(SessionFacade::written('baz'));
    }

    public function test_read_tracks_read_keys()
    {
        SessionFacade::get('foo');
        $this->assertTrue(SessionFacade::read('foo'));
    }

    public function test_read_returns_false_for_unread_keys()
    {
        $this->assertFalse(SessionFacade::read('foo'));
    }

    public function test_read_without_key_returns_overall_status()
    {
        $this->assertFalse(SessionFacade::read());
        SessionFacade::get('foo');
        $this->assertTrue(SessionFacade::read());
    }

    public function test_absolute_updates_discard_previous()
    {
        SessionFacade::set('foo', 'first');
        SessionFacade::set('foo', 'second');
        SessionFacade::set('foo', 'third');

        // Should only have one update queued
        $reflection = new \ReflectionObject(SessionFacade::session());
        $updates = $reflection->getProperty('updates');
        $updates->setAccessible(true);
        $updatesList = $updates->getValue(SessionFacade::session());

        $this->assertCount(1, $updatesList['foo']);
        $this->assertEquals('third', SessionFacade::get('foo'));
    }

    public function test_increment_after_set_uses_set_value()
    {
        SessionFacade::set('counter', 10);
        SessionFacade::increment('counter', 5);

        $this->assertEquals(15, SessionFacade::get('counter'));
    }

    public function test_set_after_increment_discards_increment()
    {
        SessionFacade::increment('counter', 5);
        SessionFacade::set('counter', 100);

        $this->assertEquals(100, SessionFacade::get('counter'));
    }

    public function test_commit_clears_internal_state()
    {
        SessionFacade::set('foo', 'bar');
        SessionFacade::commit();

        $this->assertFalse(SessionFacade::written());
        $this->assertFalse(SessionFacade::read());
    }

    public function test_update_with_custom_implementation()
    {
        SessionFacade::update(
            'items',

            new class implements \Joby\Smol\Session\SessionUpdate {

            public function apply(mixed $current_value): array
            {
                $array = is_array($current_value) ? $current_value : [];
                $array[] = 'new_item';
                return $array;
            }

            public function isAbsolute(): bool
            {
                return false;
            }

            },
        );

        $this->assertEquals(['new_item'], SessionFacade::get('items'));
    }

    public function test_storage_key_can_be_changed()
    {
        SessionFacade::setStorageKey('custom_namespace');
        $this->assertEquals('custom_namespace', SessionFacade::storageKey());

        // Reset for other tests
        SessionFacade::setStorageKey('_simple_session_data');
    }

    public function test_changing_storage_key_resets_state()
    {
        SessionFacade::set('foo', 'bar');
        SessionFacade::setStorageKey('new_namespace');

        $this->assertFalse(SessionFacade::written());
        $this->assertFalse(SessionFacade::read());

        // Reset for other tests
        SessionFacade::setStorageKey('_simple_session_data');
    }

    public function test_get_int_casts_string_to_int()
    {
        SessionFacade::set('count', '42');
        $this->assertSame(42, SessionFacade::getInt('count'));
    }

    public function test_get_int_returns_default_when_null()
    {
        $this->assertSame(10, SessionFacade::getInt('missing', 10));
    }

    public function test_require_int_throws_when_value_is_null()
    {
        $this->expectException(TypeCastException::class);
        SessionFacade::requireInt('missing');
    }

    public function test_get_float_casts_string_to_float()
    {
        SessionFacade::set('price', '19.99');
        $this->assertSame(19.99, SessionFacade::getFloat('price'));
    }

    public function test_get_bool_casts_string_to_bool()
    {
        SessionFacade::set('enabled', 'yes');
        $this->assertTrue(SessionFacade::getBool('enabled'));
    }

    public function test_get_bool_returns_default_when_null()
    {
        $this->assertFalse(SessionFacade::getBool('missing', false));
    }

    public function test_require_bool_throws_when_value_is_null()
    {
        $this->expectException(TypeCastException::class);
        SessionFacade::requireBool('missing');
    }

    public function test_get_string_casts_int_to_string()
    {
        SessionFacade::set('id', 123);
        $this->assertSame('123', SessionFacade::getString('id'));
    }

    public function test_require_string_throws_when_value_is_null()
    {
        $this->expectException(TypeCastException::class);
        SessionFacade::requireString('missing');
    }

    public function test_get_after_commit_returns_persisted_value()
    {
        SessionFacade::set('foo', 'bar');
        SessionFacade::commit();

        $this->assertEquals('bar', SessionFacade::get('foo'));
    }

}
