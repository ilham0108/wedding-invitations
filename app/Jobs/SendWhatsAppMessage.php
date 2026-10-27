<?php

namespace App\Jobs;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Enums\WhatsAppSender;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppProvider;
use App\Services\WhatsApp\WhatsAppDeliveryService;
use App\Services\WhatsApp\WhatsAppManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * One job per recipient (rules.md section 43). Retries are counted on the recipient
 * (config whatsapp.tries) so a rate-limit release never burns an attempt.
 */
class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public static function rateLimitKey(WhatsAppProvider $config): string
    {
        return 'whatsapp:send:'.$config->ulid;
    }

    public int $timeout = 60;

    public function __construct(public string $recipientUlid)
    {
        $this->onQueue(config('whatsapp.queue'));
    }

    /**
     * Rate-limit releases do not count, so bound the job by time instead of by tries.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    public function handle(WhatsAppManager $whatsapp, WhatsAppDeliveryService $delivery): void
    {
        $recipient = WhatsAppCampaignRecipient::query()->with('campaign')->where('ulid', $this->recipientUlid)->first();

        if ($recipient === null || $recipient->campaign->status !== WhatsAppCampaignStatus::Processing) {
            return;
        }

        $config = $whatsapp->configFor($recipient->campaign);

        if ($config === null) {
            $delivery->markFailed($recipient, $recipient->campaign->sender === WhatsAppSender::Own
                ? __('Your WhatsApp number is no longer connected.')
                : (new WhatsAppNotConfiguredException)->getMessage());
            $delivery->finalizeIfDone($recipient->campaign);

            return;
        }

        // Throughput limit per connection (messages per minute), so one sender never slows another.
        $rateKey = self::rateLimitKey($config);

        if (RateLimiter::tooManyAttempts($rateKey, max(1, $config->rate_per_minute))) {
            $this->release(RateLimiter::availableIn($rateKey) + 1);

            return;
        }

        // Claim the recipient so a duplicate job cannot send twice.
        $claimed = WhatsAppCampaignRecipient::query()
            ->whereKey($recipient->getKey())
            ->where('status', WhatsAppRecipientStatus::Pending)
            ->update(['status' => WhatsAppRecipientStatus::Processing, 'attempts' => $recipient->attempts + 1]);

        if ($claimed !== 1) {
            return;
        }

        RateLimiter::hit($rateKey, 60);
        $recipient->refresh();

        $started = hrtime(true);
        $result = $whatsapp->make($config)->sendMessage($recipient->phone, $recipient->message);
        $durationMs = (int) ((hrtime(true) - $started) / 1_000_000);

        $delivery->recordAttempt($recipient, $config->driver->value, $result, $durationMs);

        if ($result->success) {
            $delivery->markSent($recipient, $config, $result);
        } elseif ($result->retryable && $recipient->attempts < (int) config('whatsapp.tries')) {
            $delivery->markRetrying($recipient, $result->error);
            $backoff = (array) config('whatsapp.backoff');
            $this->release((int) ($backoff[$recipient->attempts - 1] ?? end($backoff)));

            return;
        } else {
            $delivery->markFailed($recipient, $result->error);
        }

        $delivery->finalizeIfDone($recipient->campaign);
    }

    /**
     * Unexpected exception or retry window exhausted: never leave the recipient stuck.
     */
    public function failed(?Throwable $exception): void
    {
        $recipient = WhatsAppCampaignRecipient::query()->with('campaign')->where('ulid', $this->recipientUlid)->first();

        if ($recipient === null || ! in_array($recipient->status, WhatsAppRecipientStatus::unfinished(), true)) {
            return;
        }

        $delivery = app(WhatsAppDeliveryService::class);
        $delivery->markFailed($recipient, __('Sending failed unexpectedly.'));
        $delivery->finalizeIfDone($recipient->campaign);
    }
}
