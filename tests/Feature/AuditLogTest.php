<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_is_audited_and_updates_last_login(): void
    {
        $user = User::factory()->client()->create();

        $this->withHeader('User-Agent', 'PHPUnit Browser')
            ->post('/login', ['email' => $user->email, 'password' => 'password']);

        $log = AuditLog::query()->where('action', 'auth.login')->sole();

        $this->assertTrue($log->user->is($user));
        $this->assertTrue($log->auditable->is($user));
        $this->assertSame('PHPUnit Browser', $log->user_agent);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_failed_login_is_not_audited_as_success(): void
    {
        $user = User::factory()->client()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password']);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'auth.login']);
        $this->assertNull($user->fresh()->last_login_at);
    }
}
