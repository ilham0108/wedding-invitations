<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/dashboard/settings');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/dashboard/settings', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'phone' => '+62 812 3456 7890',
                'timezone' => 'Asia/Makassar',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/settings');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('+62 812 3456 7890', $user->phone);
        $this->assertSame('Asia/Makassar', $user->timezone);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/dashboard/settings', [
                'name' => 'Test User',
                'email' => $user->email,
                'timezone' => $user->timezone,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/dashboard/settings');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/dashboard/settings', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSoftDeleted($user);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/dashboard/settings')
            ->delete('/dashboard/settings', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/dashboard/settings');

        $this->assertNotNull($user->fresh());
    }
}
