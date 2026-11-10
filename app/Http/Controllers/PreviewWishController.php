<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitWishRequest;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Services\Rsvp\PreviewGuestbook;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * The wishes form of a preview: the wish is kept for the sender only (PreviewGuestbook), never in the guestbook.
 * Access matches the preview it comes from.
 */
class PreviewWishController extends Controller
{
    /** Template preview and builder preview: published templates are public, drafts are for template admins. */
    public function template(SubmitWishRequest $request, InvitationTemplate $template, PreviewGuestbook $guestbook): RedirectResponse
    {
        abort_unless($template->isPublished() || $request->user()?->can('templates.manage'), 404);

        return $this->keep($request, $guestbook, PreviewGuestbook::templateScope($template));
    }

    /** The owner's preview of an invitation. */
    public function invitation(SubmitWishRequest $request, Invitation $invitation, PreviewGuestbook $guestbook): RedirectResponse
    {
        Gate::authorize('view', $invitation);

        return $this->keep($request, $guestbook, PreviewGuestbook::invitationScope($invitation));
    }

    private function keep(SubmitWishRequest $request, PreviewGuestbook $guestbook, string $scope): RedirectResponse
    {
        $guestbook->add($scope, trim((string) $request->validated('name')) ?: __('Guest'), trim((string) $request->validated('message')));

        return back()->with('wish_saved', 'approved')->withFragment('wishes-form');
    }
}
