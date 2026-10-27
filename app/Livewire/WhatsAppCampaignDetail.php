<?php

namespace App\Livewire;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Livewire\Concerns\Notifies;
use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppCampaignService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Campaign progress and recipients (rules.md sections 43–44). Polls while messages are going out.
 */
class WhatsAppCampaignDetail extends Component
{
    use Notifies, WithPagination;

    #[Locked]
    public string $campaignUlid = '';

    #[Url(except: '')]
    public string $status = '';

    public function mount(string $campaignUlid): void
    {
        $this->campaignUlid = $campaignUlid;
        $this->campaign();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function cancel(WhatsAppCampaignService $campaigns): void
    {
        $campaigns->cancel($this->campaign(), Auth::user());
    }

    public function sendNow(WhatsAppCampaignService $campaigns): void
    {
        $campaign = $this->campaign();

        if ($campaign->status === WhatsAppCampaignStatus::Scheduled) {
            $campaigns->dispatch($campaign);
        }
    }

    public function retryFailed(WhatsAppCampaignService $campaigns): void
    {
        $count = $campaigns->retryFailed($this->campaign(), Auth::user());

        if ($count > 0) {
            $this->notify('success', trans_choice(':count message requeued.|:count messages requeued.', $count));
        }
    }

    public function render(WhatsAppCampaignService $campaigns): View
    {
        $campaign = $this->campaign();
        $statusFilter = WhatsAppRecipientStatus::tryFrom($this->status);
        $stats = $campaigns->stats($campaign);

        return view('livewire.whats-app-campaign-detail', [
            'campaign' => $campaign,
            'stats' => $stats,
            // Colors are the validated dataviz tokens from resources/css/app.css.
            'progress' => [
                ['label' => __('Sent'), 'value' => $stats['sent'], 'color' => 'var(--viz-series-1)'],
                ['label' => __('Failed'), 'value' => $stats['failed'], 'color' => 'var(--viz-series-2)'],
                ['label' => __('Pending'), 'value' => $stats['pending'], 'color' => 'var(--viz-neutral)'],
            ],
            'recipients' => $campaign->recipients()
                ->when($statusFilter, fn ($query) => $query->where('status', $statusFilter))
                ->orderBy('name')
                ->paginate(25, ['ulid', 'name', 'phone', 'status', 'attempts', 'error', 'sent_at', 'delivered_at', 'read_at']),
            'statuses' => WhatsAppRecipientStatus::cases(),
            'polling' => $campaign->status === WhatsAppCampaignStatus::Processing,
        ]);
    }

    private function campaign(): WhatsAppCampaign
    {
        $campaign = WhatsAppCampaign::query()->where('ulid', $this->campaignUlid)->firstOrFail();
        Gate::authorize('update', $campaign);

        return $campaign;
    }
}
