<?php

namespace App\Jobs;

use App\Models\Invitation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Guest\GuestService;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Persists rows already validated by GuestImportService. Phones that appeared
 * between preview and commit are skipped instead of creating duplicates.
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

    public function handle(GuestService $guests, AuditLogger $auditLogger): void
    {
        $invitation = Invitation::query()->where('ulid', $this->invitationUlid)->firstOrFail();
        $user = User::query()->where('ulid', $this->userUlid)->firstOrFail();

        $imported = 0;
        $skipped = 0;
        $limits = app(SubscriptionLimitService::class);

        foreach (array_chunk($this->rows, 200) as $chunk) {
            DB::transaction(function () use ($chunk, $invitation, $guests, $limits, &$imported, &$skipped): void {
                foreach ($chunk as $row) {
                    // Re-checked here: guests may have been added since the preview.
                    if (($row['phone'] !== null && $guests->phoneExists($invitation, $row['phone'])) || ! $limits->canAddGuest($invitation)) {
                        $skipped++;

                        continue;
                    }

                    $guests->create($invitation, $row);
                    $imported++;
                }
            });
        }

        $auditLogger->record('guest.imported', $user, $invitation, ['imported' => $imported, 'skipped' => $skipped]);

        Log::info('Guest import processed', ['invitation' => $invitation->ulid, 'imported' => $imported, 'skipped' => $skipped]);
    }
}
