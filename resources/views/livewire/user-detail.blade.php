<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
    <div class="space-y-6">
        <x-ui.card>
            <div class="flex flex-wrap items-start gap-4">
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-sage-soft text-xl font-medium text-sage">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-display text-2xl text-ink">{{ $user->name }}</h2>
                        <x-ui.badge :variant="$user->isActive() ? 'success' : 'danger'">{{ $user->status->label() }}</x-ui.badge>
                        @foreach ($user->roles as $userRole)
                            <x-ui.badge :variant="$userRole->slug === 'super_admin' ? 'accent' : 'neutral'">{{ \App\Enums\RoleSlug::tryFrom($userRole->slug)?->label() ?? $userRole->slug }}</x-ui.badge>
                        @endforeach
                    </div>
                    <p class="mt-1 text-sm text-muted">{{ $user->email }}@if ($user->phone) · {{ $user->phone }}@endif</p>
                </div>
            </div>

            <dl class="mt-5 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                <div><dt class="text-xs text-muted">{{ __('Joined') }}</dt><dd class="text-ink">{{ $user->created_at->translatedFormat('d M Y, H:i') }}</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Last login') }}</dt><dd class="text-ink">{{ $user->last_login_at?->translatedFormat('d M Y, H:i') ?? '—' }}</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Email verified') }}</dt><dd class="text-ink">{{ $user->email_verified_at ? __('Yes') : __('No') }}</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Timezone') }}</dt><dd class="text-ink">{{ $user->timezone }}</dd></div>
                @if ($user->suspended_at)
                    <div><dt class="text-xs text-muted">{{ __('Suspended since') }}</dt><dd class="text-red-700">{{ $user->suspended_at->translatedFormat('d M Y, H:i') }}</dd></div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Invitations')" padding="p-0">
            @if ($user->invitations->isEmpty())
                <p class="p-5 text-sm text-muted">{{ __('No invitations yet.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($user->invitations as $invitation)
                        <li wire:key="user-invitation-{{ $invitation->ulid }}" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-ink">{{ $invitation->title }}</p>
                                <p class="text-xs text-muted">{{ $invitation->template?->name ?? '—' }} · {{ trans_choice(':count guest|:count guests', $invitation->guests_count) }} · {{ $invitation->created_at->translatedFormat('d M Y') }}</p>
                            </div>
                            <x-ui.badge :variant="$invitation->status->badgeVariant()">{{ $invitation->status->label() }}</x-ui.badge>
                            <x-ui.button :href="route('dashboard.invitations.preview', $invitation)" variant="ghost" size="sm" icon="fa-regular fa-eye" target="_blank">{{ __('Preview') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Recent activity')" padding="p-0">
            @if ($activity->isEmpty())
                <p class="p-5 text-sm text-muted">{{ __('No recorded activity.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($activity as $log)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 text-sm">
                            <span class="font-mono text-xs text-ink">{{ $log->action }}</span>
                            <span class="text-xs text-muted">{{ $log->ip_address }} · {{ $log->created_at->translatedFormat('d M Y, H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-line px-5 py-3 text-sm">
                    <a href="{{ route('admin.audit-logs.index', ['search' => $user->email]) }}" class="font-medium text-sage hover:underline">{{ __('See all in the audit log') }}</a>
                </div>
            @endif
        </x-ui.card>
    </div>

    <div class="space-y-4">
        <x-ui.card :title="__('Subscription')">
            @if ($current)
                <p class="font-medium text-ink">{{ $current->plan->name }}</p>
                <p class="mt-1 text-xs text-muted">
                    {{ $current->billing_period->label() }} ·
                    {{ $current->ends_at ? __('until :date', ['date' => $current->ends_at->translatedFormat('d M Y')]) : __('renews automatically') }}
                </p>
            @else
                <p class="text-sm text-ink">{{ __('Free plan') }}</p>
            @endif

            @if ($user->subscriptions->isNotEmpty())
                <p class="mb-2 mt-5 text-xs font-medium uppercase tracking-wide text-muted">{{ __('History') }}</p>
                <ul class="space-y-2 text-sm">
                    @foreach ($user->subscriptions as $subscription)
                        <li class="flex items-center justify-between gap-2">
                            <span class="min-w-0">
                                <span class="block truncate text-ink">{{ $subscription->plan->name }}</span>
                                <span class="block text-xs text-muted">{{ $subscription->starts_at->translatedFormat('d M Y') }} – {{ $subscription->ends_at?->translatedFormat('d M Y') ?? '…' }}</span>
                            </span>
                            <x-ui.badge :variant="$subscription->status->badgeVariant()">{{ $subscription->status->label() }}</x-ui.badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        @unless ($isSelf || $user->isSuperAdmin())
            <x-ui.card :title="__('Account actions')">
                @if ($user->isActive())
                    <form wire:submit="suspend" class="space-y-3">
                        <label for="suspend-reason" class="block text-sm font-medium text-ink">{{ __('Reason (optional, for the audit log)') }}</label>
                        <textarea id="suspend-reason" rows="2" maxlength="500" wire:model="reason" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage"></textarea>
                        <x-ui.button type="submit" variant="secondary" class="w-full" icon="fa-solid fa-user-lock"
                            data-confirm="{{ __('Suspend this account? The user is signed out and their invitations go offline until you activate it again.') }}">{{ __('Suspend account') }}</x-ui.button>
                    </form>
                @else
                    <x-ui.button type="button" class="w-full" icon="fa-solid fa-user-check" wire:click="activate">{{ __('Activate account') }}</x-ui.button>
                @endif

                <div class="mt-4 border-t border-line pt-4">
                    <x-ui.button type="button" variant="ghost" class="w-full text-red-600" icon="fa-regular fa-trash-can" wire:click="delete"
                        data-confirm="{{ __('Delete this account? Its invitations are removed and its subscription ends.') }}">{{ __('Delete account') }}</x-ui.button>
                </div>
            </x-ui.card>
        @endunless
    </div>
</div>
