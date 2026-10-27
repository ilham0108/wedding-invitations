<?php

namespace App\Listeners;

use App\Events\RsvpSubmitted;
use App\Notifications\RsvpReceived;

class NotifyOwnerOfRsvp
{
    public function handle(RsvpSubmitted $event): void
    {
        $event->invitation->loadMissing('user')->user->notify(new RsvpReceived($event->rsvp, $event->invitation));
    }
}
