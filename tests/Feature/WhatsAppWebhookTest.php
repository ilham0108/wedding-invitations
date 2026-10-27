<?php

namespace Tests\Feature;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppDriver;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

class WhatsAppWebhookTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private function sentRecipient(string $providerMessageId, WhatsAppProvider $provider): WhatsAppCampaignRecipient
    {
        $invitation = $this->createInvitation();
        $campaign = WhatsAppCampaign::factory()->for($invitation)->for($invitation->user)->create(['status' => WhatsAppCampaignStatus::Completed]);

        $recipient = new WhatsAppCampaignRecipient(['name' => 'Robert', 'phone' => '6281234567890', 'message' => 'Hi', 'status' => WhatsAppRecipientStatus::Sent]);
        $recipient->campaign()->associate($campaign);
        $recipient->forceFill([
            'provider_message_id' => $providerMessageId,
            'whatsapp_provider_id' => $provider->id,
            'sent_at' => now(),
        ])->save();

        return $recipient;
    }

    private function metaProvider(): WhatsAppProvider
    {
        return WhatsAppProvider::factory()->create([
            'driver' => WhatsAppDriver::Meta,
            'credentials' => ['phone_number_id' => '1', 'access_token' => 't', 'app_secret' => 'meta-app-secret', 'verify_token' => 'verify-me'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $statuses
     */
    private function metaPayload(array $statuses): string
    {
        return (string) json_encode(['entry' => [['changes' => [['value' => ['statuses' => $statuses]]]]]]);
    }

    public function test_meta_signed_status_updates_delivery_and_read(): void
    {
        $provider = $this->metaProvider();
        $recipient = $this->sentRecipient('wamid.1', $provider);

        foreach (['delivered', 'read'] as $status) {
            $body = $this->metaPayload([['id' => 'wamid.1', 'status' => $status, 'timestamp' => (string) now()->timestamp]]);

            $this->call('POST', '/webhooks/whatsapp/meta', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'meta-app-secret'),
            ], content: $body)->assertOk();
        }

        $recipient->refresh();
        $this->assertSame(WhatsAppRecipientStatus::Delivered, $recipient->status);
        $this->assertNotNull($recipient->delivered_at);
        $this->assertNotNull($recipient->read_at);
    }

    public function test_meta_rejects_unsigned_or_forged_requests(): void
    {
        $provider = $this->metaProvider();
        $recipient = $this->sentRecipient('wamid.2', $provider);
        $body = $this->metaPayload([['id' => 'wamid.2', 'status' => 'failed']]);

        $this->call('POST', '/webhooks/whatsapp/meta', server: ['CONTENT_TYPE' => 'application/json'], content: $body)->assertForbidden();
        $this->call('POST', '/webhooks/whatsapp/meta', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'wrong-secret'),
        ], content: $body)->assertForbidden();

        $this->assertSame(WhatsAppRecipientStatus::Sent, $recipient->fresh()->status);
    }

    public function test_meta_subscription_handshake(): void
    {
        $this->metaProvider();

        $this->get('/webhooks/whatsapp/meta?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=12345')
            ->assertOk()
            ->assertSee('12345');

        $this->get('/webhooks/whatsapp/meta?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=12345')->assertForbidden();
    }

    public function test_twilio_signed_callback_marks_failures(): void
    {
        $provider = WhatsAppProvider::factory()->create([
            'driver' => WhatsAppDriver::Twilio,
            'credentials' => ['account_sid' => 'AC1', 'auth_token' => 'twilio-token', 'from' => '+14155238886'],
        ]);
        $recipient = $this->sentRecipient('SM42', $provider);

        $params = ['MessageSid' => 'SM42', 'MessageStatus' => 'undelivered', 'ErrorMessage' => 'Unreachable'];
        $url = route('webhooks.whatsapp', ['driver' => 'twilio']);
        ksort($params);
        $signature = base64_encode(hash_hmac('sha1', $url.implode('', array_map(fn ($key, $value) => $key.$value, array_keys($params), $params)), 'twilio-token', true));

        $this->post($url, $params, ['X-Twilio-Signature' => 'invalid'])->assertForbidden();
        $this->post($url, $params, ['X-Twilio-Signature' => $signature])->assertOk();

        $recipient->refresh();
        $this->assertSame(WhatsAppRecipientStatus::Failed, $recipient->status);
        $this->assertSame('Unreachable', $recipient->error);
    }

    public function test_unknown_or_unconfigured_drivers_are_not_found(): void
    {
        $this->post('/webhooks/whatsapp/nope')->assertNotFound();
        $this->post('/webhooks/whatsapp/meta')->assertNotFound();

        WhatsAppProvider::factory()->fonnte()->create();
        $this->post('/webhooks/whatsapp/fonnte')->assertNotFound();
    }
}
