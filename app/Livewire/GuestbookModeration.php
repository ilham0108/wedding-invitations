<?php

namespace App\Livewire;

use App\Enums\GuestMessageStatus;
use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Services\Rsvp\GuestbookService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Guestbook moderation (rules.md section 38): approve, hide, delete, optional auto-approve.
 */
class GuestbookModeration extends Component
{
    use ResolvesEditableInvitation, WithPagination;

    #[Url(as: 'messages', except: '')]
    public string $status = '';

    public bool $autoApprove = false;

    public function mount(string $invitationUlid): void
    {
        $this->invitationUlid = $invitationUlid;
        $this->autoApprove = (bool) $this->invitation()->settings()->value('guestbook_auto_approve');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedAutoApprove(GuestbookService $guestbook): void
    {
        $guestbook->setAutoApprove($this->invitation(), $this->autoApprove);
    }

    public function approve(string $messageUlid, GuestbookService $guestbook): void
    {
        $guestbook->setStatus($this->invitation(), $messageUlid, GuestMessageStatus::Approved);
    }

    public function hide(string $messageUlid, GuestbookService $guestbook): void
    {
        $guestbook->setStatus($this->invitation(), $messageUlid, GuestMessageStatus::Hidden);
    }

    public function delete(string $messageUlid, GuestbookService $guestbook): void
    {
        $guestbook->delete($this->invitation(), $messageUlid);
    }

    public function render(): View
    {
        $invitation = $this->invitation();
        $status = GuestMessageStatus::tryFrom($this->status);

        return view('livewire.guestbook-moderation', [
            'messages' => $invitation->guestMessages()
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest()
                ->paginate(20, pageName: 'messagesPage'),
            'pendingCount' => $invitation->guestMessages()->where('status', GuestMessageStatus::Pending)->count(),
            'statuses' => GuestMessageStatus::cases(),
        ]);
    }
}
