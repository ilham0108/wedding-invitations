<?php

namespace Tests\Feature;

use App\Enums\GuestInvitationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Events\WhatsAppCampaignCompleted;
use App\Jobs\SendWhatsAppMessage;
use App\Livewire\WhatsAppCampaignDetail;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppProvider;
use App\Notifications\WhatsAppCampaignFinished;
use App\Services\WhatsApp\WhatsAppCampaignService;
use App\Services\WhatsApp\WhatsAppDeliveryService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

/**
 * End-to-end queue behavior. phpunit runs the "sync" queue, so dispatched jobs run inline.
 */
class WhatsAppQueueTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function createCampaign(Invitation $invitation, int $guests = 2): WhatsAppCampaign
    {
        Guest::factory()->count($guests)->for($invitation)->create();

        return app(WhatsAppCampaignService::class)->create($invitation, $invitation->user, [
            'name' => 'Blast',
            'message' => 'Hi {{guest_name}} {{invitation_url}}',
            'audience' => 'all',
            'schedule' => false,
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    private function fonnte(): WhatsAppProvider
    {
        return WhatsAppProvider::factory()->fonnte()->create(['rate_per_minute' => 600]);
    }

    public function test_campaign_is_sent_end_to_end_and_owner_is_notified(): void
    {
        Notification::fake();
        $this->fonnte();
        Http::fake(['api.fonnte.com/*' => Http::sequence()->push(['status' => true, 'id' => ['111']])->push(['status' => true, 'id' => ['222']])]);
        $invitation = $this->createInvitation();

        $campaign = $this->createCampaign($invitation)->refresh();

        $this->assertSame(WhatsAppCampaignStatus::Completed, $campaign->status);
        $this->assertNotNull($campaign->completed_at);
        $this->assertSame(2, $campaign->recipients()->where('status', WhatsAppRecipientStatus::Sent)->count());
        $this->assertEqualsCanonicalizing(['111', '222'], $campaign->recipients()->pluck('provider_message_id')->all());
        $this->assertSame(2, $invitation->guests()->where('invitation_status', GuestInvitationStatus::Sent)->count());
        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertSame(2, app(WhatsAppUsageService::class)->usedThisMonth($invitation->user));

        Notification::assertSentTo($invitation->user, WhatsAppCampaignFinished::class,
            fn (WhatsAppCampaignFinished $notification): bool => $notification->stats['sent'] === 2 && $notification->stats['failed'] === 0);
    }

    public function test_permanent_failures_are_marked_failed_and_can_be_retried(): void
    {
        $this->fonnte();
        Http::fake(['*' => Http::sequence()
            ->push(['status' => false, 'reason' => 'number not on WhatsApp'])
            ->push(['status' => true, 'id' => ['999']])]);
        $invitation = $this->createInvitation();

        $campaign = $this->createCampaign($invitation, 1)->refresh();
        $recipient = $campaign->recipients()->sole();

        $this->assertSame(WhatsAppCampaignStatus::Completed, $campaign->status);
        $this->assertSame(WhatsAppRecipientStatus::Failed, $recipient->status);
        $this->assertSame('number not on WhatsApp', $recipient->error);
        $this->assertSame(1, $recipient->attempts);

        Livewire::actingAs($invitation->user)
            ->test(WhatsAppCampaignDetail::class, ['campaignUlid' => $campaign->ulid])
            ->assertSee('number not on WhatsApp')
            ->call('retryFailed');

        $this->assertSame(WhatsAppRecipientStatus::Sent, $recipient->fresh()->status);
        $this->assertSame(WhatsAppCampaignStatus::Completed, $campaign->fresh()->status);
    }

    public function test_transient_failures_are_released_with_backoff_then_fail_after_max_tries(): void
    {
        $this->fonnte();
        Http::fake(['*' => Http::response('busy', 503)]);
        $recipient = $this->pendingRecipient();

        foreach ([30, 120] as $attempt => $delay) {
            $job = (new SendWhatsAppMessage($recipient->ulid))->withFakeQueueInteractions();
            $job->handle(app(WhatsAppManager::class), app(WhatsAppDeliveryService::class));

            $job->assertReleased(delay: $delay);
            $this->assertSame(WhatsAppRecipientStatus::Pending, $recipient->fresh()->status);
            $this->assertSame($attempt + 1, $recipient->fresh()->attempts);
        }

        $job = (new SendWhatsAppMessage($recipient->ulid))->withFakeQueueInteractions();
        $job->handle(app(WhatsAppManager::class), app(WhatsAppDeliveryService::class));

        $job->assertNotReleased();
        $this->assertSame(WhatsAppRecipientStatus::Failed, $recipient->fresh()->status);
        $this->assertSame(3, $recipient->fresh()->attempts);
        $this->assertSame(WhatsAppCampaignStatus::Completed, $recipient->campaign->fresh()->status);
    }

    public function test_rate_limit_releases_without_using_an_attempt(): void
    {
        $provider = WhatsAppProvider::factory()->create(['rate_per_minute' => 1]);
        $recipient = $this->pendingRecipient();
        RateLimiter::hit(SendWhatsAppMessage::rateLimitKey($provider), 60);

        $job = (new SendWhatsAppMessage($recipient->ulid))->withFakeQueueInteractions();
        $job->handle(app(WhatsAppManager::class), app(WhatsAppDeliveryService::class));

        $job->assertReleased();
        $this->assertSame(0, $recipient->fresh()->attempts);
        $this->assertSame(WhatsAppRecipientStatus::Pending, $recipient->fresh()->status);
    }

    public function test_a_recipient_is_never_sent_twice_and_cancelled_campaigns_stop(): void
    {
        $this->fonnte();
        Http::fake(['*' => Http::response(['status' => true, 'id' => ['1']])]);
        $recipient = $this->pendingRecipient();
        $manager = app(WhatsAppManager::class);
        $delivery = app(WhatsAppDeliveryService::class);

        (new SendWhatsAppMessage($recipient->ulid))->handle($manager, $delivery);
        (new SendWhatsAppMessage($recipient->ulid))->handle($manager, $delivery);

        Http::assertSentCount(1);

        $cancelled = $this->pendingRecipient();
        $cancelled->campaign->forceFill(['status' => WhatsAppCampaignStatus::Cancelled])->save();

        (new SendWhatsAppMessage($cancelled->ulid))->handle($manager, $delivery);

        Http::assertSentCount(1);
        $this->assertSame(WhatsAppRecipientStatus::Pending, $cancelled->fresh()->status);
    }

    public function test_unexpected_exceptions_mark_the_recipient_failed(): void
    {
        Event::fake([WhatsAppCampaignCompleted::class]);
        $this->fonnte();
        $recipient = $this->pendingRecipient();

        (new SendWhatsAppMessage($recipient->ulid))->failed(new \RuntimeException('boom'));

        $this->assertSame(WhatsAppRecipientStatus::Failed, $recipient->fresh()->status);
        Event::assertDispatched(WhatsAppCampaignCompleted::class);
    }

    public function test_secrets_and_full_phone_numbers_are_not_logged(): void
    {
        $this->fonnte();
        Http::fake(['*' => Http::response(['status' => true, 'id' => ['1']])]);
        $recipient = $this->pendingRecipient();

        $logged = [];
        Log::listen(function ($message) use (&$logged): void {
            $logged[] = json_encode([$message->message, $message->context]);
        });

        (new SendWhatsAppMessage($recipient->ulid))->handle(app(WhatsAppManager::class), app(WhatsAppDeliveryService::class));

        $all = implode("\n", $logged);
        $this->assertStringContainsString('WhatsApp API result', $all);
        $this->assertStringNotContainsString('fonnte-secret-token', $all);
        $this->assertStringNotContainsString($recipient->phone, $all);
    }

    /**
     * A processing campaign with one pending recipient, built without running the queue.
     */
    private function pendingRecipient(): WhatsAppCampaignRecipient
    {
        $invitation = $this->createInvitation();
        $guest = Guest::factory()->for($invitation)->create();
        $campaign = WhatsAppCampaign::factory()->for($invitation)->for($invitation->user)->create([
            'status' => WhatsAppCampaignStatus::Processing,
            'total_recipients' => 1,
        ]);

        $recipient = new WhatsAppCampaignRecipient(['name' => $guest->name, 'phone' => $guest->phone, 'message' => 'Hi', 'status' => WhatsAppRecipientStatus::Pending]);
        $recipient->campaign()->associate($campaign);
        $recipient->guest()->associate($guest);
        $recipient->save();

        return $recipient;
    }
}
