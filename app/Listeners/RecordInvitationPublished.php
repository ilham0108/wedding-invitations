<?php

namespace App\Listeners;

use App\Events\InvitationPublished;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;

class RecordInvitationPublished
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function handle(InvitationPublished $event): void
    {
        $this->auditLogger->record('invitation.published', $event->publishedBy, $event->invitation, [
            'slug' => $event->invitation->slug,
        ]);

        Log::info('Invitation published', [
            'invitation' => $event->invitation->ulid,
            'user' => $event->publishedBy->ulid,
        ]);
    }
}
