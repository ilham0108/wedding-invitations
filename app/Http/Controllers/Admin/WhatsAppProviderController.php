<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WhatsAppProviderController extends Controller
{
    public function index(): View
    {
        Gate::authorize('whatsapp-providers.manage');

        return view('admin.whatsapp.index', ['hasProviders' => WhatsAppProvider::query()->exists()]);
    }
}
