<?php

namespace App\Livewire\Concerns;

use App\Models\Invitation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/**
 * Livewire state only carries the invitation ULID (never the model or its numeric id).
 * The invitation is re-resolved and re-authorized on every request.
 */
trait ResolvesEditableInvitation
{
    #[Locked]
    public string $invitationUlid = '';

    private ?Invitation $resolvedInvitation = null;

    protected function invitation(): Invitation
    {
        if ($this->resolvedInvitation === null) {
            $invitation = Invitation::query()->where('ulid', $this->invitationUlid)->firstOrFail();
            Gate::authorize('update', $invitation);
            $this->resolvedInvitation = $invitation;
        }

        return $this->resolvedInvitation;
    }
}
