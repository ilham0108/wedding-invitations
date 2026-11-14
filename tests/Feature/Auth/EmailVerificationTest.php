<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = User::factory()->unverified()->create();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->ulid, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
    }

    public function test_verification_email_link_uses_ulid_instead_of_internal_id(): void
    {
        $user = User::factory()->unverified()->create();

        $actionUrl = (new VerifyEmail)->toMail($user)->actionUrl;

        $this->assertStringContainsString('/verify-email/'.$user->ulid.'/', $actionUrl);
        $this->assertStringNotContainsString('/verify-email/'.$user->id.'/', $actionUrl);
    }

    public function test_email_is_not_verified_with_internal_id_link(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)->get($verificationUrl)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->ulid, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_the_link_works_without_signing_in(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Your email address has been verified. Please sign in.');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest();
    }

    public function test_the_link_works_while_signed_in_to_another_account(): void
    {
        $owner = User::factory()->unverified()->create();
        $other = User::factory()->create();

        $this->actingAs($other)->get($this->verificationUrl($owner))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success');

        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
        $this->assertAuthenticatedAs($other);
    }

    public function test_unsigned_or_unknown_links_verify_nothing(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get(route('verification.verify', ['id' => $user->ulid, 'hash' => sha1($user->email)]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => '01h00000000000000000000000', 'hash' => sha1($user->email)]))->assertForbidden();
        $this->get(URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->ulid, 'hash' => sha1($user->email)]))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->ulid, 'hash' => sha1($user->email)]);
    }
}
