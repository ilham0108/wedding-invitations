<?php

namespace App\Livewire;

use App\Enums\GuestInvitationStatus;
use App\Enums\RsvpStatus;
use App\Enums\WhatsAppSender;
use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Services\WhatsApp\MessageRenderer;
use App\Services\WhatsApp\WhatsAppCampaignService;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppUsageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Create a campaign (rules.md sections 41, 42, 45). Recipients are always re-resolved
 * server-side from the invitation, so posted guest ULIDs can never reach other invitations.
 */
class WhatsAppCampaignForm extends Component
{
    use ResolvesEditableInvitation;

    public string $name = '';

    public string $templateUlid = '';

    public string $sender = 'platform';

    public string $message = '';

    public string $audience = 'all';

    /** @var array{group: string, rsvp: string, status: string} */
    public array $filters = ['group' => '', 'rsvp' => '', 'status' => 'not_sent'];

    /** @var array<int, string> */
    #[Locked]
    public array $guestUlids = [];

    public bool $schedule = false;

    public string $scheduledAt = '';

    public string $timezone = '';

    /**
     * @param  array<int, string>  $preselected
     */
    public function mount(string $invitationUlid, array $preselected = []): void
    {
        $this->invitationUlid = $invitationUlid;
        $invitation = $this->invitation();

        $this->guestUlids = array_values(array_filter($preselected, 'is_string'));
        $this->audience = $this->guestUlids !== [] ? 'selected' : 'all';
        $this->name = __('Invitation for :title', ['title' => $invitation->title]);
        $this->message = MessageRenderer::defaultTemplate();
        $this->timezone = $invitation->timezone;
        $this->sender = Auth::user()->whatsappProvider()->exists() ? WhatsAppSender::Own->value : WhatsAppSender::Platform->value;
        $this->scheduledAt = now($invitation->timezone)->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
    }

    public function updatedTemplateUlid(): void
    {
        $template = $this->templateUlid !== ''
            ? Auth::user()->whatsappTemplates()->where('ulid', $this->templateUlid)->first()
            : null;

        if ($template !== null) {
            $this->message = $template->body;
        }
    }

    public function submit(WhatsAppCampaignService $campaigns): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:'.MessageRenderer::MAX_LENGTH],
            'audience' => ['required', Rule::in(['all', 'filter', 'selected'])],
            'sender' => ['required', Rule::enum(WhatsAppSender::class)],
            'filters.rsvp' => ['nullable', Rule::enum(RsvpStatus::class)],
            'filters.status' => ['nullable', Rule::enum(GuestInvitationStatus::class)],
            'filters.group' => ['nullable', 'string', 'max:26'],
            'schedule' => ['boolean'],
            'scheduledAt' => ['required_if:schedule,true', 'nullable', 'string'],
            'timezone' => ['required', 'timezone:all'],
        ], [], [
            'scheduledAt' => mb_strtolower(__('Send at')),
            'message' => mb_strtolower(__('Message')),
        ]);

        $campaign = $campaigns->create($this->invitation(), Auth::user(), [
            'name' => $this->name,
            'message' => $this->message,
            'template_ulid' => $this->templateUlid ?: null,
            'sender' => $this->sender,
            'audience' => $this->audience,
            'filters' => $this->filters,
            'guest_ulids' => $this->guestUlids,
            'schedule' => $this->schedule,
            'scheduled_at' => $this->scheduledAt,
            'timezone' => $this->timezone,
        ]);

        session()->flash('success', $this->schedule ? __('Campaign scheduled.') : __('Campaign queued. Messages are being sent in the background.'));

        $this->redirectRoute('dashboard.whatsapp.campaigns.show', $campaign, navigate: false);
    }

    public function render(WhatsAppCampaignService $campaigns, WhatsAppManager $whatsapp, WhatsAppUsageService $usage): View
    {
        $invitation = $this->invitation();
        $user = Auth::user();

        return view('livewire.whats-app-campaign-form', [
            'summary' => $campaigns->audienceSummary($invitation, [
                'audience' => $this->audience,
                'filters' => $this->filters,
                'guest_ulids' => $this->guestUlids,
            ]),
            'preview' => $campaigns->preview($invitation, $this->message),
            'templates' => $user->whatsappTemplates()->get(['ulid', 'name']),
            'groups' => $invitation->guestGroups()->get(['ulid', 'name']),
            'rsvpStatuses' => RsvpStatus::cases(),
            'invitationStatuses' => GuestInvitationStatus::cases(),
            'variables' => MessageRenderer::variables(),
            'configured' => $whatsapp->isConfigured(),
            'ownConnection' => $whatsapp->ownConfig($user),
            'canSend' => $whatsapp->canSendAs($user, WhatsAppSender::tryFrom($this->sender) ?? WhatsAppSender::Platform),
            // The platform quota does not apply to the client's own number.
            'remaining' => $this->sender === WhatsAppSender::Platform->value ? $usage->remaining($user) : null,
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
