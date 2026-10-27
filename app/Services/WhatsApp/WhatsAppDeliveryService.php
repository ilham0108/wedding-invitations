<?php

namespace App\Services\WhatsApp;

use App\Enums\GuestInvitationStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Events\WhatsAppCampaignCompleted;
use App\Models\Guest;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\Data\DeliveryUpdate;
use App\Services\WhatsApp\Data\SendResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Applies send results and provider status updates to recipients, and closes finished campaigns.
 */
class WhatsAppDeliveryService
{
    public function recordAttempt(WhatsAppCampaignRecipient $recipient, string $driver, SendResult $result, int $durationMs): void
    {
        $recipient->attemptsLog()->create([
            'provider' => $driver,
            'attempt' => $recipient->attempts,
            'success' => $result->success,
            'http_status' => $result->httpStatus,
            'provider_message_id' => $result->messageId,
            'error' => $result->error,
            'response' => $this->trimResponse($result->response),
            'duration_ms' => $durationMs,
        ]);

        Log::info('WhatsApp API result', [
            'provider' => $driver,
            'recipient' => $recipient->ulid,
            'phone' => Str::mask($recipient->phone, '*', 4, -3),
            'attempt' => $recipient->attempts,
            'success' => $result->success,
            'http_status' => $result->httpStatus,
            'retryable' => $result->retryable,
            'duration_ms' => $durationMs,
            'error' => $result->error,
        ]);
    }

    public function markSent(WhatsAppCampaignRecipient $recipient, WhatsAppProvider $config, SendResult $result): void
    {
        $recipient->forceFill([
            'status' => WhatsAppRecipientStatus::Sent,
            'provider' => $config->driver->value,
            'whatsapp_provider_id' => $config->getKey(),
            'provider_message_id' => $result->messageId,
            'error' => null,
            'sent_at' => now(),
        ])->save();

        if ($recipient->guest_id !== null) {
            Guest::query()
                ->whereKey($recipient->guest_id)
                ->where('invitation_status', GuestInvitationStatus::NotSent)
                ->update(['invitation_status' => GuestInvitationStatus::Sent]);
        }
    }

    public function markFailed(WhatsAppCampaignRecipient $recipient, ?string $error): void
    {
        $recipient->forceFill([
            'status' => WhatsAppRecipientStatus::Failed,
            'error' => $error ? mb_substr($error, 0, 500) : null,
            'failed_at' => now(),
        ])->save();
    }

    public function markRetrying(WhatsAppCampaignRecipient $recipient, ?string $error): void
    {
        $recipient->forceFill([
            'status' => WhatsAppRecipientStatus::Pending,
            'error' => $error ? mb_substr($error, 0, 500) : null,
        ])->save();
    }

    /**
     * Apply a provider status (webhook or poll). Only messages sent through $config can be touched,
     * so one client's webhook secret can never change another client's messages.
     */
    public function applyUpdate(DeliveryUpdate $update, WhatsAppProvider $config): bool
    {
        $recipient = WhatsAppCampaignRecipient::query()
            ->where('whatsapp_provider_id', $config->getKey())
            ->where('provider_message_id', $update->messageId)
            ->first();

        if ($recipient === null) {
            return false;
        }

        $at = $update->occurredAt ?? now();

        match ($update->status) {
            DeliveryUpdate::DELIVERED => $recipient->forceFill([
                'status' => WhatsAppRecipientStatus::Delivered,
                'delivered_at' => $recipient->delivered_at ?? $at,
            ]),
            DeliveryUpdate::READ => $recipient->forceFill([
                'status' => WhatsAppRecipientStatus::Delivered,
                'delivered_at' => $recipient->delivered_at ?? $at,
                'read_at' => $recipient->read_at ?? $at,
            ]),
            DeliveryUpdate::FAILED => $recipient->forceFill([
                'status' => WhatsAppRecipientStatus::Failed,
                'error' => $update->error ? mb_substr($update->error, 0, 500) : __('The provider could not deliver the message.'),
                'failed_at' => $at,
            ]),
            default => null,
        };

        $recipient->save();

        return true;
    }

    /**
     * Close the campaign once no recipient is pending; the event fires exactly once.
     */
    public function finalizeIfDone(WhatsAppCampaign $campaign): void
    {
        $unfinished = $campaign->recipients()->whereIn('status', WhatsAppRecipientStatus::unfinished())->exists();

        if ($unfinished) {
            return;
        }

        $closed = WhatsAppCampaign::query()
            ->whereKey($campaign->getKey())
            ->where('status', WhatsAppCampaignStatus::Processing)
            ->update(['status' => WhatsAppCampaignStatus::Completed, 'completed_at' => now()]);

        if ($closed === 1) {
            WhatsAppCampaignCompleted::dispatch($campaign->refresh());
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>|null
     */
    private function trimResponse(array $response): ?array
    {
        if ($response === []) {
            return null;
        }

        $json = (string) json_encode($response);

        return strlen($json) <= 2000 ? $response : ['truncated' => mb_substr($json, 0, 2000)];
    }
}
