<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The two emails the platform sends: email verification and password reset, in the branded layout
 * (resources/views/vendor/mail) with their own wording (AppServiceProvider).
 */
class AuthEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_verification_email_is_branded_and_clear(): void
    {
        $user = User::factory()->unverified()->create(['name' => 'Ilham Pambudi']);
        $mail = (new VerifyEmail)->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame('Confirm your email address', $mail->subject);
        $this->assertSame('Confirm email address', $mail->actionText);
        $this->assertStringContainsString('Hi Ilham Pambudi,', $html);
        $this->assertStringContainsString('The link is valid for 60 minutes.', $html);
        $this->assertStringContainsString('src="'.rtrim(config('app.url'), '/').'/images/email-logo.png"', $html);
        $this->assertStringContainsString('This email was sent automatically by', $html);
        // The mail layout is Markdown: an indented line would turn into a code block.
        $this->assertStringNotContainsString('<pre', $html);
        $this->assertStringNotContainsString('laravel.com', $html);
    }

    public function test_the_reset_email_explains_what_happens_if_it_was_not_requested(): void
    {
        $user = User::factory()->create();
        $mail = (new ResetPassword('token123'))->toMail($user);

        $this->assertSame('Reset your password', $mail->subject);
        $this->assertSame('Choose a new password', $mail->actionText);
        $this->assertStringContainsString('/reset-password/token123', $mail->actionUrl);
        $this->assertContains('Did not ask for this? Ignore this email: your password stays the same.', $mail->outroLines);
    }

    public function test_in_production_a_forged_host_never_reaches_the_reset_link(): void
    {
        Notification::fake();
        // Booted as production; the request then runs as a test again (production would also demand a CSRF token).
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();
        $this->app['env'] = 'testing';
        $user = User::factory()->create();

        $this->withServerVariables(['HTTP_HOST' => 'attacker.example'])
            ->post('http://attacker.example/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, rtrim(config('app.url'), '/').'/reset-password/') && ! str_contains($url, 'attacker.example');
        });
    }
}
