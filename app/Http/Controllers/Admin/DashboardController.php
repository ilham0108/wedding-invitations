<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\RoleSlug;
use App\Enums\TemplateStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Livewire\AuditLogViewer;
use App\Models\AuditLog;
use App\Models\InvitationTemplate;
use App\Models\Order;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        // What the admin may need to act on, each linking to its filtered list. Empty ones are left out.
        $attention = array_filter([
            [
                'count' => Order::query()->where('status', OrderStatus::Pending)->count(),
                'label' => ':count order is waiting for payment|:count orders are waiting for payment',
                'action' => 'Review orders',
                'url' => route('admin.orders.index', ['status' => OrderStatus::Pending->value]),
            ],
            [
                'count' => User::query()->where('status', UserStatus::Suspended)->count(),
                'label' => ':count account is suspended|:count accounts are suspended',
                'action' => 'Review accounts',
                'url' => route('admin.users.index', ['status' => UserStatus::Suspended->value]),
            ],
            [
                'count' => InvitationTemplate::query()->where('status', TemplateStatus::Draft)->count(),
                'label' => ':count template is still a draft|:count templates are still drafts',
                'action' => 'Open templates',
                'url' => route('admin.templates.index', ['status' => TemplateStatus::Draft->value]),
            ],
        ], fn (array $item): bool => $item['count'] > 0);

        return view('admin.dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'attention' => $attention,
            'stats' => [
                'users' => User::query()->count(),
                'clients' => User::query()->withRole(RoleSlug::Client)->count(),
                'newThisMonth' => User::query()->where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'recentUsers' => User::query()
                ->with('roles:id,name,slug')
                ->latest()
                ->limit(5)
                ->get(['id', 'ulid', 'name', 'email', 'status', 'created_at']),
            // Sign-ins are left out here: they would crowd out the changes (all of them are in the audit log).
            'recentActivity' => AuditLog::query()
                ->with('user:id,name')
                ->where('action', 'not like', 'auth.%')
                ->latest('created_at')
                ->limit(8)
                ->get(['id', 'user_id', 'action', 'metadata', 'created_at']),
            'activityGroups' => AuditLogViewer::GROUPS,
        ]);
    }
}
