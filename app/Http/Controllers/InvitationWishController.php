<?php

namespace App\Http\Controllers;

use App\Enums\GuestMessageStatus;
use App\Http\Requests\SubmitWishRequest;
use App\Models\Invitation;
use App\Services\Rsvp\GuestbookService;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class InvitationWishController extends Controller
{
    public function __invoke(SubmitWishRequest $request, string $slug, GuestbookService $guestbook, SubscriptionLimitService $limits): RedirectResponse
    {
        $invitation = Invitation::query()->publiclyVisible()->where('slug', $slug)->with('user')->firstOrFail();
        abort_unless($limits->isInvitationActive($invitation), 404);

        $guest = $request->filled('token')
            ? $invitation->guests()->where('token', $request->string('token')->toString())->first()
            : null;

        if ($request->filled('token') && $guest === null) {
            return back()->withErrors(['name' => __('This personal link is no longer valid.')], 'wishes')->withFragment('wishes-form');
        }

        try {
            $message = $guestbook->submit($invitation, $request->safe()->only(['name', 'message']), $guest);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors(), 'wishes')->withInput()->withFragment('wishes-form');
        }

        return back()
            ->with('wish_saved', $message->status === GuestMessageStatus::Approved ? 'approved' : 'pending')
            ->withFragment('wishes-form');
    }
}
