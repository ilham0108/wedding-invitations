<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\WhatsAppCampaign;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppUsageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    /**
     * Campaign list for one of the user's invitations (?invitation=ULID), plus monthly usage.
     */
    public function index(Request $request, WhatsAppManager $whatsapp, WhatsAppUsageService $usage): View
    {
        Gate::authorize('viewAny', WhatsAppCampaign::class);

        [$invitations, $selected] = $this->invitations($request);

        return view('dashboard.whatsapp.index', [
            'invitations' => $invitations,
            'selected' => $selected,
            'campaigns' => $selected
                ? $selected->whatsappCampaigns()->latest()->paginate(15)->withQueryString()
                : null,
            'configured' => $whatsapp->isConfigured(),
            'ownConnection' => $whatsapp->ownConfig($request->user()),
            'usage' => [
                'used' => $usage->usedThisMonth($request->user()),
                'limit' => $usage->limit($request->user()),
                'remaining' => $usage->remaining($request->user()),
            ],
        ]);
    }

    public function create(Request $request, WhatsAppManager $whatsapp): View
    {
        Gate::authorize('viewAny', WhatsAppCampaign::class);

        [$invitations, $selected] = $this->invitations($request);
        abort_if($selected === null, 404);

        return view('dashboard.whatsapp.create', [
            'invitations' => $invitations,
            'selected' => $selected,
            'configured' => $whatsapp->isConfigured(),
            // Guests chosen in the guest list ("Send via WhatsApp"); ULIDs only.
            'preselected' => (array) $request->session()->pull('whatsapp.selected_guests.'.$selected->ulid, []),
        ]);
    }

    public function show(WhatsAppCampaign $campaign): View
    {
        Gate::authorize('view', $campaign);

        return view('dashboard.whatsapp.show', ['campaign' => $campaign->load('invitation:id,ulid,title')]);
    }

    public function templates(): View
    {
        Gate::authorize('viewAny', WhatsAppCampaign::class);

        return view('dashboard.whatsapp.templates');
    }

    public function connection(): View
    {
        Gate::authorize('viewAny', WhatsAppCampaign::class);

        return view('dashboard.whatsapp.connection');
    }

    /**
     * @return array{0: Collection<int, Invitation>, 1: ?Invitation}
     */
    private function invitations(Request $request): array
    {
        $invitations = $request->user()->invitations()->latest('updated_at')->get(['id', 'ulid', 'user_id', 'title']);
        $selected = $invitations->firstWhere('ulid', $request->string('invitation')->toString()) ?? $invitations->first();

        if ($selected !== null) {
            Gate::authorize('update', $selected);
        }

        return [$invitations, $selected];
    }
}
