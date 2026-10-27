<?php

namespace App\Services;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;

class UserRegistrationService
{
    public function __construct(private AuditLogger $auditLogger, private SystemSettings $settings) {}

    /**
     * Create a client account and assign its default role in a single transaction.
     * The account starts in the platform's default timezone (admin setting).
     * $trusted holds non-fillable attributes vouched for by the caller (e.g. a Google login).
     *
     * @param  array{name: string, email: string, password: ?string}  $attributes
     * @param  array<string, mixed>  $trusted
     */
    public function registerClient(array $attributes, array $trusted = []): User
    {
        $user = DB::transaction(function () use ($attributes, $trusted): User {
            $user = User::query()->make([...$attributes, 'timezone' => $this->settings->get('default_timezone')]);
            $user->forceFill($trusted)->save();
            $user->assignRole(RoleSlug::Client);

            return $user;
        });

        $this->auditLogger->record('auth.registered', $user, $user);

        event(new Registered($user));

        return $user;
    }
}
