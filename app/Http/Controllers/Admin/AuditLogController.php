<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        Gate::authorize('audit-logs.view');

        return view('admin.audit-logs.index');
    }
}
