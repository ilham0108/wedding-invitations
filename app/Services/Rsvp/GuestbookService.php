<?php

namespace App\Services\Rsvp;

use App\Enums\GuestMessageStatus;
use App\Models\Guest;
use App\Models\GuestMessage;
use App\Models\Invitation;
use Illuminate\Validation\ValidationException;

/**
 * Guestbook (rules.md section 38): guests submit, the client approves, hides, or deletes.
 */
class GuestbookService
{
    /**
     * @param  array{name?: ?string, message: string}  $data
     *
     * @throws ValidationException
     */
    public function submit(Invitation $invitation, array $data, ?Guest $guest = null): GuestMessage
    {
        $enabled = $invitation->sections()->where('type', 'wishes')->where('is_enabled', true)->exists();

        if (! $enabled || $invitation->hasExpired()) {
            throw ValidationException::withMessages(['message' => __('Wishes are closed for this invitation.')])->errorBag('wishes');
        }

        $autoApprove = (bool) $invitation->settings()->value('guestbook_auto_approve');

        $message = new GuestMessage([
            'name' => $guest?->name ?? trim((string) $data['name']),
            'message' => trim($data['message']),
            'status' => $autoApprove ? GuestMessageStatus::Approved : GuestMessageStatus::Pending,
        ]);
        $message->invitation()->associate($invitation);
        $message->guest()->associate($guest);
        $message->save();

        return $message;
    }

    public function setStatus(Invitation $invitation, string $messageUlid, GuestMessageStatus $status): void
    {
        $this->find($invitation, $messageUlid)->update(['status' => $status]);
    }

    public function delete(Invitation $invitation, string $messageUlid): void
    {
        $this->find($invitation, $messageUlid)->delete();
    }

    public function setAutoApprove(Invitation $invitation, bool $enabled): void
    {
        $invitation->settings()->updateOrCreate([], ['guestbook_auto_approve' => $enabled]);
    }

    /**
     * Approved wishes for the public page, as plain arrays (newest first).
     *
     * @return array<int, array{name: string, message: string, date: string}>
     */
    public function approvedForDisplay(Invitation $invitation, int $limit = 50): array
    {
        return $invitation->guestMessages()
            ->approved()
            ->latest()
            ->limit($limit)
            ->get(['name', 'message', 'created_at'])
            ->map(fn (GuestMessage $message): array => [
                'name' => $message->name,
                'message' => $message->message,
                'date' => $message->created_at->diffForHumans(),
            ])
            ->all();
    }

    private function find(Invitation $invitation, string $messageUlid): GuestMessage
    {
        return $invitation->guestMessages()->where('ulid', $messageUlid)->firstOrFail();
    }
}
