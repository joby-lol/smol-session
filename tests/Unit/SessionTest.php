<?php

/**
 * smolSession
 * https://github.com/joby-lol/smol-session
 * (c) 2025 Joby Elliott code@joby.lol
 * MIT License https://opensource.org/licenses/MIT
 */

namespace Joby\Smol\Session\Tests\Unit;

use Joby\Smol\Session\Session;
use Joby\Smol\Session\SystemSessionHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SessionTest extends TestCase
{

    private SystemSessionHelper&MockObject $system;

    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->system = $this->createMock(SystemSessionHelper::class);
        $this->session = new Session(system: $this->system);
    }

    public function test_get_loads_data_and_marks_read(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_ACTIVE);

        $this->system->expects($this->once())
            ->method('session_start');

        $this->system->expects($this->once())
            ->method('session_abort');

        $this->system->data = [
            '_smol_session_data' => ['user_id' => 42],
        ];

        $this->assertFalse($this->session->read('user_id'));
        $this->assertSame(42, $this->session->get('user_id'));
        $this->assertTrue($this->session->read('user_id'));
        $this->assertTrue($this->session->read());
    }

    public function test_get_applies_queued_updates_locally(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_ACTIVE);

        $this->system->data = [
            '_smol_session_data' => ['counter' => 10],
        ];

        $this->session->increment('counter', 5);

        $this->assertTrue($this->session->written('counter'));
        $this->assertSame(15, $this->session->get('counter'));
    }

    public function test_set_and_set_if_null(): void
    {
        $this->session->set('foo', 'bar');
        $this->assertTrue($this->session->written('foo'));

        $this->session->setIfNull('baz', 'qux');
        $this->assertTrue($this->session->written('baz'));
    }

    public function test_unset_skips_if_value_is_null(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_NONE);

        $this->session->unset('non_existent');
        $this->assertFalse($this->session->written('non_existent'));
    }

    public function test_unset_queues_update_if_value_exists(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_ACTIVE);

        $this->system->data = [
            '_smol_session_data' => ['existing' => 'value'],
        ];

        $this->session->unset('existing');
        $this->assertTrue($this->session->written('existing'));
        $this->assertNull($this->session->get('existing'));
    }

    public function test_commit_does_nothing_if_no_updates_queued(): void
    {
        $this->system->expects($this->never())
            ->method('session_start');

        $this->session->commit();
    }

    public function test_commit_starts_applies_updates_and_closes_session(): void
    {
        $this->system->expects($this->atLeastOnce())
            ->method('session_start');

        $this->system->expects($this->once())
            ->method('session_write_close');

        $this->system->data = [
            '_smol_session_data' => [
                'counter'   => 1,
                'to_remove' => 'bye',
            ],
        ];

        $this->session->set('name', 'Alice');
        $this->session->increment('counter', 2);
        $this->session->unset('to_remove');

        $this->session->commit();

        $this->assertSame('Alice', $this->system->data['_smol_session_data']['name']);
        $this->assertSame(3, $this->system->data['_smol_session_data']['counter']);
        $this->assertArrayNotHasKey('to_remove', $this->system->data['_smol_session_data']);

        $this->assertFalse($this->session->written());
        $this->assertFalse($this->session->read());
    }

    public function test_commit_throws_exception_if_storage_key_is_not_array(): void
    {
        $this->system->expects($this->once())
            ->method('session_start');

        $this->system->data = [
            '_smol_session_data' => 'not an array',
        ];

        $this->session->set('foo', 'bar');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session storage key "_smol_session_data" is not an array.');

        $this->session->commit();
    }

    public function test_destroy_clears_state_and_calls_system_methods_when_session_exists(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_ACTIVE);

        $this->system->expects($this->once())
            ->method('session_start');

        $this->system->expects($this->once())
            ->method('session_name')
            ->willReturn('PHPSESSID');

        $this->system->expects($this->once())
            ->method('session_get_cookie_params')
            ->willReturn([
                'path'     => '/',
                'domain'   => 'example.com',
                'secure'   => true,
                'httponly' => true,
            ]);

        $this->system->expects($this->once())
            ->method('setcookie')
            ->with(
                'PHPSESSID',
                '',
                $this->callback(fn(int $expire) => $expire <= time()),
                '/',
                'example.com',
                true,
                true,
            );

        $this->system->expects($this->once())
            ->method('session_destroy');

        $this->session->set('foo', 'bar');
        $this->session->destroy();

        $this->assertFalse($this->session->written());
    }

    public function test_destroy_early_return_when_no_session_exists(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_NONE);

        $this->system->expects($this->once())
            ->method('session_name')
            ->willReturn('PHPSESSID');

        $this->system->expects($this->never())
            ->method('session_start');

        $this->system->expects($this->never())
            ->method('session_destroy');

        $this->session->destroy();
    }

    public function test_rotate_commits_and_regenerates_session_id(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_ACTIVE);

        $this->system->expects($this->exactly(2))
            ->method('session_start');

        $this->system->expects($this->exactly(2))
            ->method('session_write_close');

        $this->system->expects($this->once())
            ->method('session_regenerate_id')
            ->willReturn(true);

        $this->system->data = [
            '_smol_session_data' => [],
        ];

        $this->session->set('key', 'value');
        $this->session->rotate(true);

        $this->assertFalse($this->session->written());
    }

    public function test_set_storage_key_resets_internal_state(): void
    {
        $this->assertSame('_smol_session_data', $this->session->storageKey());

        $this->session->set('foo', 'bar');
        $this->assertTrue($this->session->written('foo'));

        $this->session->setStorageKey('custom_storage_key');

        $this->assertSame('custom_storage_key', $this->session->storageKey());
        $this->assertFalse($this->session->written('foo'));
    }

    public function test_exists_checks_session_status_cookie_and_data(): void
    {
        $this->system->expects($this->once())
            ->method('session_status')
            ->willReturn(PHP_SESSION_NONE);

        $this->system->expects($this->once())
            ->method('session_name')
            ->willReturn('PHPSESSID');

        $this->assertFalse($this->session->exists());
    }

}
