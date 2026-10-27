<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.google.client_id' => 'client-id', 'services.google.client_secret' => 'client-secret']);
    }

    public function test_google_button_is_shown_only_when_configured(): void
    {
        $this->get('/login')->assertOk()->assertSee(route('auth.google.redirect'));
        $this->get('/register')->assertOk()->assertSee(route('auth.google.redirect'));

        config(['services.google.client_id' => null]);

        $this->get('/login')->assertOk()->assertDontSee(route('auth.google.redirect'));
        $this->get('/auth/google/redirect')->assertNotFound();
    }

    public function test_redirect_sends_the_visitor_to_google(): void
    {
        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://accounts.google.com/', $response->headers->get('Location'));
    }

    public function test_new_google_user_gets_a_verified_client_account(): void
    {
        Notification::fake();
        $this->fakeGoogleUser('google-1', 'new@example.com', 'New Person');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-1', $user->google_id);
        $this->assertSame('New Person', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertFalse($user->hasPassword());
        $this->assertTrue($user->hasRole(RoleSlug::Client));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'auth.registered']);
        Notification::assertNotSentTo($user, VerifyEmail::class);

        $this->get('/dashboard')->assertOk();
    }

    public function test_existing_account_with_the_same_email_is_linked(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ana@example.com']);
        $this->fakeGoogleUser('google-2', 'ana@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-2', $user->fresh()->google_id);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(1, User::query()->count());
    }

    public function test_linked_account_logs_in_even_after_changing_its_email(): void
    {
        $user = User::factory()->create(['email' => 'changed@example.com']);
        $user->forceFill(['google_id' => 'google-3'])->save();
        $this->fakeGoogleUser('google-3', 'original@gmail.com');

        $this->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
    }

    public function test_unverified_google_email_is_refused(): void
    {
        User::factory()->create(['email' => 'victim@example.com']);
        $this->fakeGoogleUser('google-4', 'victim@example.com', verified: false);

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_suspended_account_is_refused(): void
    {
        $user = User::factory()->create(['email' => 'blocked@example.com', 'status' => UserStatus::Suspended]);
        $this->fakeGoogleUser('google-5', 'blocked@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error', __('This account has been suspended.'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_deleted_account_cannot_come_back_through_google(): void
    {
        User::factory()->create(['email' => 'gone@example.com'])->delete();
        $this->fakeGoogleUser('google-6', 'gone@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_no_account_is_created_while_registration_is_closed(): void
    {
        app(SystemSettings::class)->save(['registration_enabled' => false], User::factory()->superAdmin()->create(), app(AuditLogger::class));
        $this->fakeGoogleUser('google-7', 'late@example.com');

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'late@example.com']);
    }

    public function test_failed_google_handshake_returns_to_login(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

        $this->get('/auth/google/callback')->assertRedirect(route('login'))->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_google_user_can_set_a_password_without_a_current_one(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)->get('/dashboard/settings')->assertOk()->assertDontSee('name="current_password"', false);

        $this->actingAs($user)
            ->from('/dashboard/settings')
            ->put('/password', ['password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasPassword());
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'new-password']);
        $this->assertAuthenticated();
    }

    public function test_passwordless_account_cannot_log_in_with_an_empty_password(): void
    {
        $user = User::factory()->create(['password' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => ''])->assertSessionHasErrors();
        $this->post('/login', ['email' => $user->email, 'password' => 'anything']);

        $this->assertGuest();
    }

    private function fakeGoogleUser(string $id, string $email, string $name = 'Google User', bool $verified = true): void
    {
        $googleUser = (new SocialiteUser)
            ->setRaw(['sub' => $id, 'email' => $email, 'email_verified' => $verified])
            ->map(['id' => $id, 'name' => $name, 'email' => $email]);

        $provider = Mockery::mock(GoogleProvider::class);
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }
}
