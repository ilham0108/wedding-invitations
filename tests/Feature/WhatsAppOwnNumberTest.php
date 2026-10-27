<?php

namespace Tests\Feature;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppDriver;
use App\Enums\WhatsAppRecipientStatus;
use App\Enums\WhatsAppSender;
use App\Jobs\SendWhatsAppMessage;
use App\Livewire\WhatsAppCampaignForm;
use App\Livewire\WhatsAppConnection;
use App\Livewire\WhatsAppProviderManager;
use App\Models\Guest;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppCampaignService;
use App\Services\WhatsApp\WhatsAppDeliveryService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

/**
 * Clients sending invitations from their own WhatsApp number.
 */
class WhatsAppOwnNumberTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, RefreshDatabase;

    private function connect(User $user, string $token = 'client-token'): WhatsAppProvider
    {
        $provider = new WhatsAppProvider([
            'driver' => WhatsAppDriver::Fonnte,
            'name' => 'My WA',
            'credentials' => ['token' => $token],
            'rate_per_minute' => 60,
            'is_active' => true,
        ]);
        $provider->user()->associate($user)->save();

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function campaignData(array $overrides = []): array
    {
        return [
            'name' => 'Blast',
            'message' => 'Hi {{guest_name}}',
            'sender' => 'own',
            'audience' => 'all',
            'schedule' => false,
            'timezone' => 'Asia/Jakarta',
            ...$overrides,
        ];
    }

    public function test_client_connects_their_number_without_exposing_the_token(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->get('/dashboard/whatsapp/connection')->assertOk()->assertSeeLivewire(WhatsAppConnection::class);

        $component = Livewire::actingAs($user)
            ->test(WhatsAppConnection::class)
            ->set('driver', 'fonnte')
            ->set('name', 'WA Ilham')
            ->set('credentials.token', 'very-secret-device-token')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('connected', true)
            ->assertSet('credentials.token', '')
            ->assertSee('WA Ilham');

        $this->assertStringNotContainsString('very-secret-device-token', json_encode($component->snapshot));
        $this->assertStringNotContainsString('very-secret-device-token', $component->html());

        $provider = $user->whatsappProvider()->sole();
        $this->assertSame('very-secret-device-token', $provider->credential('token'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'whatsapp.connection_created', 'user_id' => $user->id]);
    }

    public function test_tutorial_video_is_shown_only_when_configured(): void
    {
        $user = User::factory()->client()->create();

        config(['whatsapp.tutorials.fonnte' => null]);
        Livewire::actingAs($user)->test(WhatsAppConnection::class)->assertDontSee('Video tutorial: connect Fonnte');

        config(['whatsapp.tutorials.fonnte' => 'https://youtu.be/dQw4w9WgXcQ']);
        Livewire::actingAs($user)->test(WhatsAppConnection::class)
            ->assertSee('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1', false)
            ->assertSee('Play video tutorial');

        config(['whatsapp.tutorials.fonnte' => '/videos/fonnte-tutorial.mp4']);
        Livewire::actingAs($user)->test(WhatsAppConnection::class)
            ->assertSee('<video src="/videos/fonnte-tutorial.mp4"', false);

        config(['whatsapp.tutorials.fonnte' => 'javascript:alert(1)']);
        Livewire::actingAs($user)->test(WhatsAppConnection::class)->assertDontSee('javascript:alert(1)', false);
    }

    public function test_clients_cannot_use_the_log_driver_or_exceed_the_safe_rate(): void
    {
        Livewire::actingAs(User::factory()->client()->create())
            ->test(WhatsAppConnection::class)
            ->set('driver', 'log')
            ->set('name', 'Fake')
            ->call('save')
            ->assertHasErrors('driver')
            ->set('driver', 'fonnte')
            ->set('credentials.token', 'x')
            ->set('ratePerMinute', 500)
            ->call('save')
            ->assertHasErrors('ratePerMinute');

        $this->assertSame(0, WhatsAppProvider::query()->count());
    }

    public function test_a_client_connection_is_never_a_platform_provider(): void
    {
        $client = User::factory()->client()->create();
        $this->connect($client);

        $this->assertNull(app(WhatsAppManager::class)->activeConfig());

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(WhatsAppProviderManager::class)
            ->assertDontSee('My WA')
            ->assertSee('No WhatsApp provider yet.');

        $platform = WhatsAppProvider::factory()->create();
        app(WhatsAppManager::class)->activate($platform);

        $this->assertTrue($client->whatsappProvider()->sole()->is_active, 'Activating a platform provider must not touch client connections.');
    }

    public function test_own_number_campaign_sends_with_the_client_token_and_skips_the_quota(): void
    {
        WhatsAppProvider::factory()->create(['name' => 'Platform log']);
        Http::fake(['api.fonnte.com/*' => Http::response(['status' => true, 'id' => ['55']])]);

        $invitation = $this->createInvitation();
        $this->subscribe($invitation->user, ['max_whatsapp_messages' => 1]);
        Guest::factory()->count(3)->for($invitation)->create();
        $connection = $this->connect($invitation->user, 'client-device-token');

        $campaign = app(WhatsAppCampaignService::class)->create($invitation, $invitation->user, $this->campaignData())->refresh();

        $this->assertSame(WhatsAppSender::Own, $campaign->sender);
        $this->assertSame(WhatsAppCampaignStatus::Completed, $campaign->status);
        $this->assertSame(3, $campaign->recipients()->where('status', WhatsAppRecipientStatus::Sent)->count());
        $this->assertSame([$connection->id], $campaign->recipients()->distinct()->pluck('whatsapp_provider_id')->all());
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'client-device-token'));

        $this->assertSame(0, app(WhatsAppUsageService::class)->usedThisMonth($invitation->user));

        // The same three guests through the platform number exceed the quota of 1.
        $this->expectException(ValidationException::class);
        app(WhatsAppCampaignService::class)->create($invitation, $invitation->user, $this->campaignData(['sender' => 'platform']));
    }

    public function test_own_number_requires_a_connection(): void
    {
        WhatsAppProvider::factory()->create();
        $invitation = $this->createInvitation();
        Guest::factory()->for($invitation)->create();

        Livewire::actingAs($invitation->user)
            ->test(WhatsAppCampaignForm::class, ['invitationUlid' => $invitation->ulid])
            ->assertSet('sender', 'platform')
            ->set('sender', 'own')
            ->assertSee('Connect your WhatsApp number first.')
            ->call('submit')
            ->assertHasErrors(['sender' => 'Connect your WhatsApp number first.']);

        $this->assertSame(0, WhatsAppCampaign::query()->count());
    }

    public function test_form_defaults_to_the_own_number_when_connected(): void
    {
        $invitation = $this->createInvitation();
        $this->connect($invitation->user);

        Livewire::actingAs($invitation->user)
            ->test(WhatsAppCampaignForm::class, ['invitationUlid' => $invitation->ulid])
            ->assertSet('sender', 'own')
            ->assertSee('My WA')
            ->assertDontSee('messages left this month');
    }

    public function test_disconnecting_fails_remaining_messages_without_falling_back_to_the_platform(): void
    {
        WhatsAppProvider::factory()->create(['name' => 'Platform log']);
        Http::fake();
        $invitation = $this->createInvitation();
        $guest = Guest::factory()->for($invitation)->create();
        $this->connect($invitation->user);

        $campaign = WhatsAppCampaign::factory()->for($invitation)->for($invitation->user)->create([
            'status' => WhatsAppCampaignStatus::Processing,
            'sender' => WhatsAppSender::Own,
            'total_recipients' => 1,
        ]);
        $recipient = new WhatsAppCampaignRecipient(['name' => $guest->name, 'phone' => $guest->phone, 'message' => 'Hi', 'status' => WhatsAppRecipientStatus::Pending]);
        $recipient->campaign()->associate($campaign)->save();

        Livewire::actingAs($invitation->user)
            ->test(WhatsAppConnection::class)
            ->assertSee('1 running campaigns use this number.')
            ->call('disconnect')
            ->assertSet('connected', false);

        (new SendWhatsAppMessage($recipient->ulid))->handle(app(WhatsAppManager::class), app(WhatsAppDeliveryService::class));

        $recipient->refresh();
        $this->assertSame(WhatsAppRecipientStatus::Failed, $recipient->status);
        $this->assertSame('Your WhatsApp number is no longer connected.', $recipient->error);
        $this->assertNull($recipient->provider);
        Http::assertNothingSent();
    }

    public function test_a_client_webhook_cannot_update_messages_of_other_senders(): void
    {
        $alice = User::factory()->client()->create();
        $aliceMeta = new WhatsAppProvider([
            'driver' => WhatsAppDriver::Meta,
            'name' => 'Alice Meta',
            'credentials' => ['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'alice-secret'],
            'is_active' => true,
        ]);
        $aliceMeta->user()->associate($alice)->save();

        $platformMeta = WhatsAppProvider::factory()->create(['driver' => WhatsAppDriver::Meta, 'credentials' => ['app_secret' => 'platform-secret']]);

        $own = $this->sentRecipientVia($aliceMeta, 'wamid.alice');
        $foreign = $this->sentRecipientVia($platformMeta, 'wamid.platform');

        $body = (string) json_encode(['entry' => [['changes' => [['value' => ['statuses' => [
            ['id' => 'wamid.alice', 'status' => 'delivered'],
            ['id' => 'wamid.platform', 'status' => 'failed'],
        ]]]]]]]);

        $this->call('POST', route('webhooks.whatsapp.connection', ['driver' => 'meta', 'provider' => $aliceMeta->ulid]), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'alice-secret'),
        ], content: $body)->assertOk();

        $this->assertSame(WhatsAppRecipientStatus::Delivered, $own->fresh()->status);
        $this->assertSame(WhatsAppRecipientStatus::Sent, $foreign->fresh()->status, 'Alice must not be able to touch platform messages.');

        // A platform provider ULID is not reachable through the connection URL.
        $this->post(route('webhooks.whatsapp.connection', ['driver' => 'meta', 'provider' => $platformMeta->ulid]))->assertNotFound();
    }

    private function sentRecipientVia(WhatsAppProvider $provider, string $messageId): WhatsAppCampaignRecipient
    {
        $invitation = $this->createInvitation();
        $campaign = WhatsAppCampaign::factory()->for($invitation)->for($invitation->user)->create(['status' => WhatsAppCampaignStatus::Completed]);

        $recipient = new WhatsAppCampaignRecipient(['name' => 'Guest', 'phone' => '6281234567890', 'message' => 'Hi', 'status' => WhatsAppRecipientStatus::Sent]);
        $recipient->campaign()->associate($campaign);
        $recipient->forceFill(['provider_message_id' => $messageId, 'whatsapp_provider_id' => $provider->id, 'sent_at' => now()])->save();

        return $recipient;
    }
}
