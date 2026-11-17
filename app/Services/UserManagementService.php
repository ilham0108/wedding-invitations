<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin actions on accounts (rules.md section 64). Suspended owners cannot sign in and their
 * invitations are offline for guests; deleting an account removes its invitations and guest data
 * for good and frees the email. Admins cannot act on themselves or on other super admins.
 */
class UserManagementService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private AccountDeletionService $accounts,
    ) {}

    /**
     * @throws ValidationException
     */
    public function suspend(User $user, User $admin, ?string $reason = null): void
    {
        $this->guard($user, $admin);

        $user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now()])->save();

        $this->auditLogger->record('user.suspended', $admin, $user, array_filter([
            'email' => $user->email,
            'reason' => Str::limit(trim((string) $reason), 500, '') ?: null,
        ]));
    }

    public function activate(User $user, User $admin): void
    {
        $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null])->save();

        $this->auditLogger->record('user.activated', $admin, $user, ['email' => $user->email]);
    }

    /**
     * Same permanent deletion as a client deleting their own account (AccountDeletionService).
     *
     * @throws ValidationException
     */
    public function delete(User $user, User $admin): void
    {
        $this->guard($user, $admin);

        $this->accounts->delete($user, $admin);
    }

    /**
     * @throws ValidationException
     */
    private function guard(User $user, User $admin): void
    {
        if ($user->is($admin)) {
            throw ValidationException::withMessages(['user' => __('You cannot do this to your own account.')]);
        }

        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages(['user' => __('Super admin accounts cannot be suspended or deleted here.')]);
        }
    }
}
