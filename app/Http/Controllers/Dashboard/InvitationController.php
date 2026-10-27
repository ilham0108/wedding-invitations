<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Services\AuditLogger;
use App\Services\Invitation\InvitationRenderer;
use App\Services\Invitation\StoryCard;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invitation::class);

        $user = $request->user();
        $invitations = $user->invitations()->with('template:id,name,is_premium')->latest('updated_at')->paginate(12);
        $limits = app(SubscriptionLimitService::class);

        return view('dashboard.invitations.index', [
            'invitations' => $invitations,
            // ULIDs of invitations guests cannot open on the current plan.
            'offline' => $invitations->getCollection()
                ->reject(fn (Invitation $invitation): bool => $limits->isInvitationActive($invitation->setRelation('user', $user)))
                ->pluck('ulid')
                ->all(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Invitation::class);

        return view('dashboard.invitations.create', [
            'preselectedTemplate' => $request->string('template')->toString() ?: null,
        ]);
    }

    public function edit(Invitation $invitation): View
    {
        Gate::authorize('update', $invitation);

        return view('dashboard.invitations.edit', ['invitation' => $invitation]);
    }

    /**
     * Full preview, or the bare page when embedded in the editor iframe (?embed=1).
     */
    public function preview(Request $request, Invitation $invitation, InvitationRenderer $renderer): View
    {
        Gate::authorize('view', $invitation);

        $rendered = $renderer->forInvitation($invitation, isPreview: true);

        if ($request->boolean('embed')) {
            return view('invitation.show', ['invitation' => $rendered, 'embedded' => true]);
        }

        return view('invitation.show', [
            'invitation' => $rendered,
            'story' => app(StoryCard::class)->forGuests($rendered, $invitation),
            'previewLabel' => __('Preview: :title', ['title' => $invitation->title]),
            'backUrl' => route('dashboard.invitations.edit', $invitation),
        ]);
    }

    public function destroy(Request $request, Invitation $invitation, AuditLogger $auditLogger): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $invitation->delete();
        $auditLogger->record('invitation.deleted', $request->user(), $invitation);

        return to_route('dashboard.invitations.index')->with('success', __('Invitation deleted.'));
    }
}
