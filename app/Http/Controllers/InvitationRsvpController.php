<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitRsvpRequest;
use App\Models\Invitation;
use App\Services\Rsvp\RsvpService;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class InvitationRsvpController extends Controller
{
    public function __invoke(SubmitRsvpRequest $request, string $slug, RsvpService $rsvps, SubscriptionLimitService $limits): RedirectResponse
    {
        $invitation = Invitation::query()->publiclyVisible()->where('slug', $slug)->with('user')->firstOrFail();
        abort_unless($limits->isInvitationActive($invitation), 404);

        $guest = $request->filled('token')
            ? $invitation->guests()->where('token', $request->string('token')->toString())->first()
            : null;

        if ($request->filled('token') && $guest === null) {
            return back()->withErrors(['name' => __('This personal link is no longer valid.')], 'rsvp')->withFragment('rsvp-form');
        }

        try {
            $rsvps->submit($invitation, $request->safe()->except(['token', 'website']), $guest);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors(), 'rsvp')->withInput()->withFragment('rsvp-form');
        }

        return back()->with('rsvp_saved', true)->withFragment('rsvp-form');
    }
}
