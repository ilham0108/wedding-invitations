<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Enums\WhatsAppSender;
use App\Jobs\ProcessWhatsAppCampaign;
use App\Models\Guest;
use App\Models\Invitation;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Services\AuditLogger;
use App\Services\Guest\GuestService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bulk WhatsApp flow (rules.md section 42): create campaign → create recipients → queue jobs.
 * Nothing is ever sent inside the HTTP request.
 */
class WhatsAppCampaignService
{
    public function __construct(
        private WhatsAppManager $whatsapp,
        private MessageRenderer $renderer,
        private WhatsAppUsageService $usage,
        private GuestService $guests,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Guests the campaign would reach, before the "has a phone number" filter.
     *
     * @param  array{audience: string, filters?: array<string, ?string>, guest_ulids?: array<int, string>}  $audience
     */
    public function audienceQuery(Invitation $invitation, array $audience): HasMany
    {
        return match ($audience['audience']) {
            'selected' => $invitation->guests()->whereIn('ulid', $audience['guest_ulids'] ?? []),
            'filter' => $this->guests->filteredQuery($invitation, $audience['filters'] ?? []),
            default => $invitation->guests(),
        };
    }

    /**
     * @param  array{audience: string, filters?: array<string, ?string>, guest_ulids?: array<int, string>}  $audience
     * @return array{total: int, reachable: int}
     */
    public function audienceSummary(Invitation $invitation, array $audience): array
    {
        return [
            'total' => $this->audienceQuery($invitation, $audience)->count(),
            'reachable' => $this->audienceQuery($invitation, $audience)->whereNotNull('phone')->count(),
        ];
    }

    /**
     * @param  array{name: string, message: string, template_ulid?: ?string, sender?: string, audience: string, filters?: array<string, ?string>, guest_ulids?: array<int, string>, schedule: bool, scheduled_at?: ?string, timezone: string}  $data
     *
     * @throws ValidationException
     */
    public function create(Invitation $invitation, User $user, array $data): WhatsAppCampaign
    {
        $sender = WhatsAppSender::tryFrom($data['sender'] ?? WhatsAppSender::Platform->value) ?? WhatsAppSender::Platform;

        if (! $this->whatsapp->canSendAs($user, $sender)) {
            throw ValidationException::withMessages(['sender' => $sender === WhatsAppSender::Own
                ? __('Connect your WhatsApp number first.')
                : __('WhatsApp provider is not configured.')]);
        }

        if ($unknown = $this->renderer->unknownVariables($data['message'])) {
            throw ValidationException::withMessages(['message' => __('Unknown variables: :list.', ['list' => implode(', ', $unknown)])]);
        }

        $scheduledAt = $data['schedule'] ? $this->scheduledAt($data['scheduled_at'] ?? null, $data['timezone']) : null;

        $guests = $this->audienceQuery($invitation, $data)
            ->whereNotNull('phone')
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'token']);

        if ($guests->isEmpty()) {
            throw ValidationException::withMessages(['audience' => __('None of the selected guests has a WhatsApp number.')]);
        }

        if ($sender->countsTowardQuota() && ! $this->usage->canSend($user, $guests->count())) {
            throw ValidationException::withMessages(['audience' => __('This campaign needs :count messages but only :remaining are left this month.', [
                'count' => $guests->count(),
                'remaining' => $this->usage->remaining($user),
            ])]);
        }

        $campaign = DB::transaction(function () use ($invitation, $user, $data, $guests, $scheduledAt, $sender): WhatsAppCampaign {
            $campaign = new WhatsAppCampaign([
                'name' => trim($data['name']),
                'message' => $data['message'],
                'status' => $scheduledAt ? WhatsAppCampaignStatus::Scheduled : WhatsAppCampaignStatus::Draft,
                'sender' => $sender,
                'timezone' => $data['timezone'],
                'scheduled_at' => $scheduledAt,
            ]);
            $campaign->user()->associate($user);
            $campaign->invitation()->associate($invitation);

            if (! empty($data['template_ulid'])) {
                $campaign->template()->associate($user->whatsappTemplates()->where('ulid', $data['template_ulid'])->first());
            }

            $campaign->total_recipients = $guests->count();
            $campaign->save();

            $this->createRecipients($campaign, $invitation, $guests->all());

            return $campaign;
        });

        $this->auditLogger->record('whatsapp.campaign_created', $user, $campaign, [
            'recipients' => $campaign->total_recipients,
            'sender' => $sender->value,
            'scheduled_at' => $scheduledAt?->toIso8601String(),
        ]);

        if ($scheduledAt === null) {
            $this->dispatch($campaign);
        }

        return $campaign->refresh();
    }

    /**
     * Move a draft or due scheduled campaign into processing and hand it to the queue.
     */
    public function dispatch(WhatsAppCampaign $campaign): bool
    {
        $claimed = WhatsAppCampaign::query()
            ->whereKey($campaign->getKey())
            ->whereIn('status', [WhatsAppCampaignStatus::Draft, WhatsAppCampaignStatus::Scheduled])
            ->update(['status' => WhatsAppCampaignStatus::Processing, 'started_at' => now()]);

        if ($claimed === 1) {
            ProcessWhatsAppCampaign::dispatch($campaign->ulid);

            $campaign->loadMissing('user');
            $this->auditLogger->record('whatsapp.campaign_sent', $campaign->user, $campaign, ['name' => $campaign->name, 'recipients' => $campaign->total_recipients]);
        }

        return $claimed === 1;
    }

    public function cancel(WhatsAppCampaign $campaign, User $user): void
    {
        if (! $campaign->status->isCancellable()) {
            return;
        }

        $campaign->forceFill(['status' => WhatsAppCampaignStatus::Cancelled, 'cancelled_at' => now()])->save();

        $this->auditLogger->record('whatsapp.campaign_cancelled', $user, $campaign);
    }

    /**
     * Requeue failed recipients of a finished campaign.
     *
     * @throws ValidationException
     */
    public function retryFailed(WhatsAppCampaign $campaign, User $user): int
    {
        $failed = $campaign->recipients()->where('status', WhatsAppRecipientStatus::Failed);
        $count = $failed->count();

        if ($count === 0 || $campaign->status !== WhatsAppCampaignStatus::Completed) {
            return 0;
        }

        if ($campaign->sender->countsTowardQuota() && ! $this->usage->canSend($user, $count)) {
            throw ValidationException::withMessages(['retry' => __('Not enough messages left this month to retry :count recipients.', ['count' => $count])]);
        }

        DB::transaction(function () use ($campaign, $failed): void {
            $failed->update(['status' => WhatsAppRecipientStatus::Pending, 'attempts' => 0, 'error' => null, 'failed_at' => null]);
            $campaign->forceFill(['status' => WhatsAppCampaignStatus::Draft, 'completed_at' => null])->save();
        });

        $this->dispatch($campaign);

        return $count;
    }

    /**
     * Progress numbers for rules.md section 44.
     *
     * @return array{total: int, pending: int, sent: int, delivered: int, failed: int, opened: int, clicked: int}
     */
    public function stats(WhatsAppCampaign $campaign): array
    {
        $count = fn (array $statuses): int => $campaign->recipients()->whereIn('status', $statuses)->count();

        return [
            'total' => $campaign->total_recipients,
            'pending' => $count(WhatsAppRecipientStatus::unfinished()),
            'sent' => $count(WhatsAppRecipientStatus::successful()),
            'delivered' => $count([WhatsAppRecipientStatus::Delivered]),
            'failed' => $count([WhatsAppRecipientStatus::Failed]),
            'opened' => $campaign->recipients()->whereNotNull('read_at')->count(),
            // Guests who opened their personal link after this campaign reached them.
            'clicked' => $campaign->recipients()
                ->whereNotNull('sent_at')
                ->whereHas('guest', fn ($query) => $query->whereColumn('guests.opened_at', '>=', 'whatsapp_campaign_recipients.sent_at'))
                ->count(),
        ];
    }

    /**
     * Preview of the first recipient's message.
     */
    public function preview(Invitation $invitation, string $message, ?Guest $guest = null): string
    {
        $guest ??= $invitation->guests()->whereNotNull('phone')->orderBy('name')->first() ?? new Guest(['name' => __('Guest name')]);

        if ($guest->token === null) {
            $guest->token = str_repeat('x', Guest::TOKEN_LENGTH);
        }

        return $this->renderer->render($message, $guest, $invitation, $this->renderer->eventContext($invitation));
    }

    /**
     * @param  array<int, Guest>  $guests
     */
    private function createRecipients(WhatsAppCampaign $campaign, Invitation $invitation, array $guests): void
    {
        $event = $this->renderer->eventContext($invitation);
        $now = now();

        foreach (array_chunk($guests, 500) as $chunk) {
            WhatsAppCampaignRecipient::query()->insert(array_map(fn (Guest $guest): array => [
                'ulid' => Str::lower((string) Str::ulid()),
                'whatsapp_campaign_id' => $campaign->getKey(),
                'guest_id' => $guest->getKey(),
                'name' => $guest->name,
                'phone' => $guest->phone,
                'message' => $this->renderer->render($campaign->message, $guest, $invitation, $event),
                'status' => WhatsAppRecipientStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }

    /**
     * @throws ValidationException
     */
    private function scheduledAt(?string $local, string $timezone): CarbonImmutable
    {
        try {
            $scheduledAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i', (string) $local, $timezone);
        } catch (\Throwable) {
            $scheduledAt = null;
        }

        if (! $scheduledAt instanceof CarbonImmutable) {
            throw ValidationException::withMessages(['scheduled_at' => __('Choose a valid date and time.')]);
        }

        if ($scheduledAt->isPast()) {
            throw ValidationException::withMessages(['scheduled_at' => __('The scheduled time must be in the future.')]);
        }

        return $scheduledAt->utc();
    }
}
