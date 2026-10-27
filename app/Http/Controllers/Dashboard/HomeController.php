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

        return view('dashboard.home', [
            'user' => $user,
            'stats' => [
                'invitations' => $user->invitations()->count(),
                'published' => $user->invitations()->where('status', InvitationStatus::Published)->count(),
                'guests' => Guest::query()->whereHas('invitation', fn ($query) => $query->where('user_id', $user->id))->count(),
            ],
            'recentInvitations' => $user->invitations()
                ->with('template:id,name')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
        ]);
    }
}
