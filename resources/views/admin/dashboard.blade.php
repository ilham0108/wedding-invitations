{{-- Admin home: what needs acting on first, then the figures, then who joined and what changed. --}}
<x-layouts.app area="admin" :title="__('Admin Dashboard')">
    <section aria-labelledby="attention-heading" class="rounded-xl border border-line bg-white">
        <h2 id="attention-heading" class="border-b border-line px-6 py-4 text-sm font-semibold text-ink">{{ __('Needs your attention') }}</h2>
        @if ($attention === [])
            <p class="px-6 py-5 text-sm text-ink-soft">
                <i class="fa-solid fa-check mr-2 text-sage" aria-hidden="true"></i>{{ __('Nothing is waiting for you: no unpaid orders, suspended accounts or draft templates.') }}
            </p>
        @else
            <ul class="divide-y divide-line">
                @foreach ($attention as $item)
                    <li class="flex flex-wrap items-center justify-between gap-x-6 px-6 py-1.5">
                        <p class="text-sm text-ink">{{ trans_choice($item['label'], $item['count'], ['count' => Number::format($item['count'])]) }}</p>
                        <a href="{{ $item['url'] }}" class="inline-flex min-h-11 items-center text-sm font-medium text-sage underline-offset-4 hover:underline">{{ __($item['action']) }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4">
        <x-ui.stat-card :label="__('Users')" :value="Number::format($stats['users'])" :href="route('admin.users.index')" />
        <x-ui.stat-card :label="__('Clients')" :value="Number::format($stats['clients'])" :href="route('admin.users.index', ['role' => \App\Enums\RoleSlug::Client->value])" />
        <x-ui.stat-card :label="__('New this month')" :value="Number::format($stats['newThisMonth'])" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        <x-ui.card :title="__('Newest users')" class="lg:col-span-3" padding="p-0">
            @if ($recentUsers->isEmpty())
                <x-ui.empty-state icon="fa-solid fa-users" :title="__('No users yet.')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentUsers as $recentUser)
                        <li>
                            <a href="{{ route('admin.users.show', $recentUser) }}" class="flex items-center gap-3 px-6 py-3 transition hover:bg-paper">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sage-soft text-xs font-semibold uppercase text-sage" aria-hidden="true">{{ mb_substr($recentUser->name, 0, 1) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-ink">{{ $recentUser->name }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $recentUser->email }} · {{ $recentUser->created_at->translatedFormat('d M Y') }}</span>
                                </span>
                                {{-- Only what is out of the ordinary gets a badge: admins and suspended accounts. --}}
                                @if ($recentUser->isSuperAdmin())
                                    <x-ui.badge variant="accent">{{ __('Admin') }}</x-ui.badge>
                                @endif
                                @unless ($recentUser->isActive())
                                    <x-ui.badge variant="danger">{{ $recentUser->status->label() }}</x-ui.badge>
                                @endunless
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Recent changes')" class="lg:col-span-2" padding="p-0">
            @if ($recentActivity->isEmpty())
                <x-ui.empty-state icon="fa-solid fa-clock-rotate-left" :title="__('No changes recorded yet.')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($recentActivity as $activity)
                        @php
                            $group = \Illuminate\Support\Str::before($activity->action, '.');
                            $subject = collect(['slug', 'section', 'name', 'plan', 'number'])->map(fn (string $key) => $activity->metadata[$key] ?? null)->first(fn ($value) => is_string($value) && $value !== '');
                        @endphp
                        <li class="px-6 py-3">
                            <p class="text-sm text-ink">
                                {{ __($activityGroups[$group] ?? 'Other') }}@if ($subject): <span class="text-ink-soft">{{ $subject }}</span>@endif
                            </p>
                            <p class="text-xs text-muted">{{ $activity->user?->name ?? __('System') }} · {{ $activity->created_at->diffForHumans() }}</p>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.audit-logs.index') }}" class="flex min-h-11 items-center border-t border-line px-6 text-sm font-medium text-sage hover:bg-paper">{{ __('Open the audit log') }}</a>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
