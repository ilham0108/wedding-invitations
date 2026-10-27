<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppCampaign;

class WhatsAppCampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('whatsapp-campaigns.manage');
    }

    public function view(User $user, WhatsAppCampaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    public function update(User $user, WhatsAppCampaign $campaign): bool
    {
        return $this->owns($user, $campaign);
    }

    private function owns(User $user, WhatsAppCampaign $campaign): bool
    {
        return $user->hasPermission('whatsapp-campaigns.manage')
            && ($campaign->user_id === $user->id || $user->hasPermission('invitations.manage-any'));
    }
}
