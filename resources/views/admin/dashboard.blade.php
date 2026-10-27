<x-layouts.app area="admin" :title="__('Admin Dashboard')">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat-card :label="__('Users')" :value="Number::format($stats['users'])" icon="fa-solid fa-users" />
        <x-ui.stat-card :label="__('Clients')" :value="Number::format($stats['clients'])" icon="fa-solid fa-user-tie" />
        <x-ui.stat-card :label="__('Suspended')" :value="Number::format($stats['suspended'])" icon="fa-solid fa-user-lock" />
        <x-ui.stat-card :label="__('New this month')" :value="Number::format($stats['newThisMonth'])" icon="fa-solid fa-user-plus" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <x-ui.card :title="__('Newest users')" class="lg:col-span-3" padding="p-0">
            @if ($recentUsers->isEmpty())
                <x-ui.empty-state icon="fa-solid fa-users" :title="__('No users yet.')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentUsers as $recentUser)
                        <li class="flex items-center gap-3 px-6 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sage-soft text-xs font-semibold uppercase text-sage">{{ mb_substr($recentUser->name, 0, 1) }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-ink">{{ $recentUser->name }}</p>
                                <p class="truncate text-xs text-muted">{{ $recentUser->email }}</p>
                            </div>
                            <div class="hidden gap-1 sm:flex">
                                @foreach ($recentUser->roles as $role)
                                    <x-ui.badge>{{ $role->name }}</x-ui.badge>
                                @endforeach
                            </div>
                            <x-ui.badge :variant="$recentUser->isActive() ? 'success' : 'danger'">{{ $recentUser->status->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Recent activity')" class="lg:col-span-2" padding="p-0">
            @if ($recentActivity->isEmpty())
                <x-ui.empty-state icon="fa-solid fa-clock-rotate-left" :title="__('No activity recorded yet.')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentActivity as $activity)
                        <li class="px-6 py-3">
                            <p class="text-sm text-ink"><span class="font-medium">{{ $activity->user?->name ?? __('System') }}</span> · {{ $activity->action }}</p>
                            <p class="text-xs text-muted">{{ $activity->created_at->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
