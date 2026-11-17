<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\InvitationStatus;
use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user->loadCount([
            'invitations',
            'invitations as published_invitations_count' => fn ($query) => $query->where('status', InvitationStatus::Published),
        ]);

        return view('dashboard.home', [
            'user' => $user,
            'stats' => [
                'invitations' => (int) $user->invitations_count,
                'published' => (int) $user->published_invitations_count,
                // whereIn on the indexed invitation_id instead of a correlated EXISTS per guest row.
                'guests' => Guest::query()->whereIn('invitation_id', $user->invitations()->select('id'))->count(),
            ],
            'recentInvitations' => $user->invitations()
                ->with('template:id,name')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
