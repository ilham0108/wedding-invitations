<?php

namespace App\Notifications;

use App\Enums\RsvpAttendance;
use App\Models\Invitation;
use App\Models\Rsvp;
use Illuminate\Notifications\Notification;

/**
 * In-app notification (rules.md section 61). Stored data carries ULIDs only.
 */
class RsvpReceived extends Notification
{
    public function __construct(
        public Rsvp $rsvp,
        public Invitation $invitation,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $answer = $this->rsvp->attendance === RsvpAttendance::Attending
            ? trans_choice(':name will attend (:count person).|:name will attend (:count people).', $this->rsvp->number_of_guests, ['name' => $this->rsvp->name])
            : __(':name cannot attend.', ['name' => $this->rsvp->name]);

        return [
            'title' => __('New RSVP for :title', ['title' => $this->invitation->title]),
            'message' => $answer,
            'url' => route('dashboard.rsvp.index', ['invitation' => $this->invitation->ulid]),
        ];
    }
}
