<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin actions on accounts (rules.md section 64). Suspended owners cannot sign in and their
 * invitations are offline for guests; deleting an account soft-deletes it with its invitations
 * and ends its subscription. Admins cannot act on themselves or on other super admins.
 */
class UserManagementService
{
    public function __construct(private AuditLogger $auditLogger) {}

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
     * @throws ValidationException
     */
    public function delete(User $user, User $admin): void
    {
        $this->guard($user, $admin);

        DB::transaction(function () use ($user): void {
            $user->invitations()->each(fn (Invitation $invitation) => $invitation->delete());
            $user->subscriptions()->current()->update(['status' => SubscriptionStatus::Cancelled, 'ends_at' => now()]);
            $user->delete();
        });

        app(SubscriptionLimitService::class)->forget($user);

        $this->auditLogger->record('user.deleted', $admin, $user, ['email' => $user->email]);
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
