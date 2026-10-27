<?php

namespace Tests\Concerns;

use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;

trait CreatesInvitations
{
    /**
     * @param  array<int, string|array<string, mixed>>  $sections
     */
    protected function createInvitation(?User $owner = null, array $sections = ['cover', 'couple', 'gallery', 'closing']): Invitation
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => $sections]]);

        return app(InvitationService::class)->createFromTemplate($owner ?? User::factory()->client()->create(), $template, [
            'title' => 'John & Jane',
            'event_type' => 'wedding',
            'timezone' => 'Asia/Jakarta',
        ]);
    }
}
