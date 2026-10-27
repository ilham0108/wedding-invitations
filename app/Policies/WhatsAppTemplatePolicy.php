<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppTemplate;

class WhatsAppTemplatePolicy
{
    public function update(User $user, WhatsAppTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }

    public function delete(User $user, WhatsAppTemplate $template): bool
    {
        return $template->user_id === $user->id;
    }
}
