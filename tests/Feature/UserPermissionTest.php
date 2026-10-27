<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_has_client_permissions_only(): void
    {
        $client = User::factory()->client()->create();

        $this->assertTrue($client->hasPermission('invitations.create'));
        $this->assertFalse($client->hasPermission('users.manage'));
        $this->assertTrue(Gate::forUser($client)->allows('guests.manage'));
        $this->assertTrue(Gate::forUser($client)->denies('templates.manage'));
    }

    public function test_super_admin_has_admin_and_client_permissions(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->assertTrue($admin->hasPermission('users.manage'));
        $this->assertTrue($admin->hasPermission('invitations.create'));
        $this->assertTrue(Gate::forUser($admin)->allows('audit-logs.view'));
    }

    public function test_user_without_role_has_no_permissions(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->hasPermission('invitations.create'));
        $this->assertTrue(Gate::forUser($user)->denies('invitations.create'));
    }
}
