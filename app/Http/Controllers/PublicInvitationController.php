<?php

namespace App\Http\Controllers;

use App\Enums\GuestInvitationStatus;
use App\Models\Guest;
use App\Models\Invitation;
use App\Services\Invitation\InvitationRenderer;
use App\Services\Invitation\StoryCard;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Http\Response;

class PublicInvitationController extends Controller
{
    /**
     * Resolved per call, not in the constructor: the router reuses controller instances,
     * and the limit service memoizes per request.
     */
    private function limits(): SubscriptionLimitService
    {
        return app(SubscriptionLimitService::class);
    }

    public function show(string $slug, InvitationRenderer $renderer): Response
    {
        $invitation = $this->findVisible($slug);

        return $this->unavailable($invitation) ?? $this->render($invitation, $renderer);
    }

    /**
     * Personal link (rules.md section 31). An unknown token falls back to the
     * generic invitation so a mistyped link never reveals whether a guest exists.
     */
    public function personal(string $slug, string $token, InvitationRenderer $renderer): Response
    {
        $invitation = $this->findVisible($slug);

        if ($response = $this->unavailable($invitation)) {
            return $response;
        }

        $guest = strlen($token) === 32
            ? $invitation->guests()->where('token', $token)->first(['id', 'name', 'token', 'number_of_guests', 'invitation_status', 'opened_at'])
            : null;

        if ($guest !== null && $guest->opened_at === null) {
            $guest->forceFill(['invitation_status' => GuestInvitationStatus::Opened, 'opened_at' => now()])->save();
        }

        return $this->render($invitation, $renderer, $guest);
    }

    private function findVisible(string $slug): Invitation
    {
        return Invitation::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->with('user')
            ->firstOrFail();
    }

    /**
     * Expired events, invitations of suspended accounts, and invitations the owner's plan no longer
     * covers are not shown to guests. Guests never see why; the owner sees it in the dashboard.
     */
    private function unavailable(Invitation $invitation): ?Response
    {
        if ($invitation->hasExpired()) {
            return response()->view('invitation.expired', ['title' => $invitation->title], 410);
        }

        if ($invitation->user === null || ! $invitation->user->isActive() || ! $this->limits()->isInvitationActive($invitation)) {
            return response()->view('invitation.offline', ['title' => $invitation->title], 403)
                ->header('Cache-Control', 'no-store');
        }

        return null;
    }

    private function render(Invitation $invitation, InvitationRenderer $renderer, ?Guest $guest = null): Response
    {
        $rendered = $renderer->forInvitation($invitation, guest: $guest);

        $response = response()->view('invitation.show', [
            'invitation' => $rendered,
            'showBranding' => $this->limits()->showsBranding($invitation->user),
            'story' => app(StoryCard::class)->forGuests($rendered, $invitation),
        ]);

        // Personalized pages must never be cached by shared proxies.
        return $guest !== null ? $response->header('Cache-Control', 'private, no-store') : $response;
    }
}
