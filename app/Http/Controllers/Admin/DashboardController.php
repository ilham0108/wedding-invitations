<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'stats' => [
                'users' => User::query()->count(),
                'clients' => User::query()->withRole(RoleSlug::Client)->count(),
                'suspended' => User::query()->where('status', UserStatus::Suspended)->count(),
                'newThisMonth' => User::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'recentUsers' => User::query()
                ->with('roles:id,name,slug')
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'name', 'email', 'status', 'created_at']),
            'recentActivity' => AuditLog::query()
                ->with('user:id,name')
                ->latest('created_at')
                ->limit(8)
                ->get(['id', 'user_id', 'action', 'ip_address', 'created_at']),
        ]);
    }
}
