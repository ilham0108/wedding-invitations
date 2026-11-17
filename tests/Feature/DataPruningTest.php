<?php

namespace Tests\Feature;

use App\Enums\WhatsAppRecipientStatus;
use App\Models\AuditLog;
use App\Models\CacheEntry;
use App\Models\Guest;
use App\Models\GuestMessage;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppMessage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

/**
 * Scheduled housekeeping: model:prune keeps each table within its retention period.
 */
class DataPruningTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function prune(): void
    {
        $this->artisan('model:prune')->assertSuccessful();
    }

    public function test_deleted_guests_and_wishes_are_removed_after_thirty_days(): void
    {
        $invitation = $this->createInvitation();
        [$old, $recent, $active] = Guest::factory()->for($invitation)->count(3)->create();
        [$oldWish, $recentWish] = GuestMessage::factory()->for($invitation)->count(2)->create();

        $this->travel(-31)->days();
        $old->delete();
        $oldWish->delete();
        $this->travelBack();
        $this->travel(-29)->days();
        $recent->delete();
        $recentWish->delete();
        $this->travelBack();

        $this->prune();

        $this->assertSame([$recent->id, $active->id], Guest::withTrashed()->orderBy('id')->pluck('id')->all());
        $this->assertSame([$recentWish->id], GuestMessage::withTrashed()->pluck('id')->all());
    }

    public function test_logs_and_read_notifications_are_removed_after_their_retention(): void
    {
        $user = User::factory()->create();
        $recipient = WhatsAppCampaign::factory()->create()->recipients()->create([
            'name' => 'Ana', 'phone' => '6281234567890', 'message' => 'Hi', 'status' => WhatsAppRecipientStatus::Sent,
        ]);
        $apiLog = function (int $daysAgo) use ($recipient): WhatsAppMessage {
            $this->travelTo(now()->subDays($daysAgo));
            $message = $recipient->attemptsLog()->create(['provider' => 'fonnte', 'attempt' => 1, 'success' => true]);
            $this->travelBack();

            return $message;
        };
        $audit = function (int $daysAgo) use ($user): AuditLog {
            $this->travelTo(now()->subDays($daysAgo));
            $log = AuditLog::query()->create(['user_id' => $user->id, 'action' => 'test.action']);
            $this->travelBack();

            return $log;
        };
        $notification = fn (?int $readDaysAgo): Notification => Notification::query()->create([
            'id' => (string) Str::uuid(),
            'type' => 'test',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => [],
            'read_at' => $readDaysAgo === null ? null : now()->subDays($readDaysAgo),
            'created_at' => now()->subDays(400),
        ]);

        $keptLog = $apiLog(89);
        $apiLog(91);
        $keptAudit = $audit(364);
        $audit(366);
        $unread = $notification(null);
        $recentlyRead = $notification(89);
        $notification(91);

        $this->prune();

        $this->assertSame([$keptLog->id], WhatsAppMessage::query()->pluck('id')->all());
        $this->assertSame([$keptAudit->id], AuditLog::query()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$unread->id, $recentlyRead->id], $user->notifications()->pluck('id')->all());
    }

    public function test_expired_rows_of_the_database_cache_are_removed(): void
    {
        CacheEntry::query()->insert([
            ['key' => 'expired', 'value' => 's:1:"a";', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'fresh', 'value' => 's:1:"b";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->prune();

        $this->assertSame(['fresh'], CacheEntry::query()->pluck('key')->all());
    }

    public function test_housekeeping_commands_are_scheduled_daily(): void
    {
        $scheduled = collect(app(Schedule::class)->events())
            ->mapWithKeys(fn ($event): array => [trim(str_replace(["'", '"'], '', Str::after((string) $event->command, 'artisan'))) => $event->expression])
            ->all();

        $this->assertSame('0 3 * * *', $scheduled['model:prune'] ?? null);
        $this->assertSame('15 3 * * *', $scheduled['queue:prune-failed --hours=720'] ?? null);
        $this->assertSame('20 3 * * *', $scheduled['auth:clear-resets'] ?? null);
    }
}
