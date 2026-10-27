<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RsvpController extends Controller
{
    /**
     * RSVP dashboard and guestbook for one of the user's invitations (?invitation=ULID).
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Invitation::class);

        $invitations = $request->user()->invitations()->latest('updated_at')->get(['id', 'ulid', 'user_id', 'title']);
        $selected = $invitations->firstWhere('ulid', $request->string('invitation')->toString()) ?? $invitations->first();

        if ($selected !== null) {
            Gate::authorize('update', $selected);
        }

        return view('dashboard.rsvp.index', [
            'invitations' => $invitations,
            'selected' => $selected,
            'tab' => $request->string('tab')->toString() === 'guestbook' ? 'guestbook' : 'responses',
        ]);
    }
}
