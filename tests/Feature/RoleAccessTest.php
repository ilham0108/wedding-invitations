<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_from_dashboard_and_admin(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_client_can_open_dashboard_but_not_admin_panel(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get('/dashboard')->assertOk();
        $this->actingAs($client)->get('/admin')->assertForbidden();
    }

    public function test_super_admin_can_open_admin_panel(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Admin Dashboard');
    }

    public function test_super_admin_is_redirected_to_admin_panel_after_login(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard', absolute: false));
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        $user = User::factory()->client()->suspended()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_user_with_active_session_is_signed_out(): void
    {
        $user = User::factory()->client()->suspended()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_dashboard_pages_never_render_internal_user_ids(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $client = User::factory()->client()->create();

        $response = $this->actingAs($admin)->get('/admin');

        $response->assertOk()->assertDontSee('"id":'.$client->id, false);
        $this->assertArrayNotHasKey('id', $client->toArray());
        $this->assertSame($client->ulid, $client->getRouteKey());
    }
}
