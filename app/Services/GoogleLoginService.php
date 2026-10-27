<?php

namespace App\Services;

use App\Exceptions\SocialLoginException;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleLoginService
{
    public function __construct(
        private UserRegistrationService $registration,
        private SystemSettings $settings,
        private AuditLogger $auditLogger,
    ) {}

    public function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * Resolve the account for a Google profile: the linked account, an existing account with the
     * same (Google-verified) email which then gets linked, or a new client account.
     *
     * @throws SocialLoginException
     */
    public function resolveUser(SocialiteUser $googleUser): User
    {
        $email = strtolower((string) $googleUser->getEmail());

        if ($email === '' || ! ($googleUser->getRaw()['email_verified'] ?? false)) {
            throw new SocialLoginException(__('Your Google account has no verified email address.'));
        }

        $user = User::withTrashed()->where('google_id', $googleUser->getId())->first()
            ?? User::withTrashed()->where('email', $email)->first();

        if ($user?->trashed()) {
            throw new SocialLoginException(__('This account is no longer available.'));
        }

        if ($user === null) {
            return $this->register($googleUser, $email);
        }

        if (! $user->isActive()) {
            throw new SocialLoginException(__('This account has been suspended.'));
        }

        if ($user->google_id === null) {
            $user->forceFill(['google_id' => $googleUser->getId(), 'email_verified_at' => $user->email_verified_at ?? now()])->save();

            $this->auditLogger->record('auth.google_linked', $user, $user);
        }

        return $user;
    }

    /**
     * @throws SocialLoginException
     */
    private function register(SocialiteUser $googleUser, string $email): User
    {
        if (! $this->settings->registrationOpen()) {
            throw new SocialLoginException(__('Registration is currently closed.'));
        }

        return $this->registration->registerClient(
            ['name' => $googleUser->getName() ?: strstr($email, '@', true), 'email' => $email, 'password' => null],
            ['google_id' => $googleUser->getId(), 'email_verified_at' => now()],
        );
    }
}
