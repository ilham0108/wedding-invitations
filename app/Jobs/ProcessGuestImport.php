<?php

namespace App\Jobs;

use App\Models\Invitation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Guest\GuestImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Persists rows already validated by GuestImportService. Safe to retry: the rows are saved
 * in one transaction, and guests that already exist are skipped (GuestImportService::persist).
 */
class ProcessGuestImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<int, array{name: string, phone: ?string, email: ?string, group: ?string, number_of_guests: int}>  $rows
     */
    public function __construct(
        public string $invitationUlid,
        public string $userUlid,
        public array $rows,
    ) {}

    public function handle(GuestImportService $imports, AuditLogger $auditLogger): void
    {
        $invitation = Invitation::query()->where('ulid', $this->invitationUlid)->firstOrFail();
        $user = User::query()->where('ulid', $this->userUlid)->firstOrFail();

        ['imported' => $imported, 'skipped' => $skipped] = $imports->persist($invitation, $this->rows);

        $auditLogger->record('guest.imported', $user, $invitation, ['imported' => $imported, 'skipped' => $skipped]);

        Log::info('Guest import processed', ['invitation' => $invitation->ulid, 'imported' => $imported, 'skipped' => $skipped]);
    }
}
