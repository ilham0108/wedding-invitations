<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invitations.manage-own');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invitations.create');
    }

    public function view(User $user, Invitation $invitation): bool
    {
        return $this->ownsOrManagesAny($user, $invitation);
    }

    public function update(User $user, Invitation $invitation): bool
    {
        return $this->ownsOrManagesAny($user, $invitation);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $this->ownsOrManagesAny($user, $invitation);
    }

    private function ownsOrManagesAny(User $user, Invitation $invitation): bool
    {
        return $invitation->user_id === $user->id || $user->hasPermission('invitations.manage-any');
    }
}
