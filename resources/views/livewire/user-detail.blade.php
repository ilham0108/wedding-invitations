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
                <div><dt class="text-xs text-muted">{{ __('Last login') }}</dt><dd class="text-ink">{{ $user->last_login_at?->translatedFormat('d M Y, H:i') ?? __('Never') }}</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Email verified') }}</dt><dd class="text-ink">{{ $user->email_verified_at ? __('Yes') : __('No') }}</dd></div>
                <div><dt class="text-xs text-muted">{{ __('Timezone') }}</dt><dd class="text-ink">{{ $user->timezone }}</dd></div>
                @if ($user->suspended_at)
                    <div><dt class="text-xs text-muted">{{ __('Suspended since') }}</dt><dd class="text-red-700">{{ $user->suspended_at->translatedFormat('d M Y, H:i') }}</dd></div>
                @endif
            </dl>
        </x-ui.card>

        <x-ui.card :title="__('Invitations')" padding="p-0">
            @if ($invitations->isEmpty())
                <p class="p-5 text-sm text-muted">{{ __('No invitations yet.') }}</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($invitations as $invitation)
                        <li wire:key="user-invitation-{{ $invitation->ulid }}" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                            <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                                <p class="truncate font-medium text-ink">{{ $invitation->title }}</p>
                                <p class="text-xs text-muted">{{ $invitation->template?->name ?? __('No template') }} · {{ trans_choice(':count guest|:count guests', $invitation->guests_count) }} · {{ $invitation->created_at->translatedFormat('d M Y') }}</p>
                            </div>
                            <x-ui.badge :variant="$invitation->status->badgeVariant()">{{ $invitation->status->label() }}</x-ui.badge>
                            <x-ui.button :href="route('dashboard.invitations.preview', $invitation)" variant="ghost" size="sm" icon="fa-regular fa-eye" target="_blank">{{ __('Preview') }}</x-ui.button>
                        </li>
                    @endforeach
                </ul>
                @if ($invitations->hasPages())
                    <div class="border-t border-line px-4 py-3">{{ $invitations->links() }}</div>
                @endif
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
                    <x-ui.button type="button" variant="ghost" class="w-full text-red-600" icon="fa-regular fa-trash-can"
                        x-data x-on:click="$dispatch('open-modal', 'confirm-admin-user-deletion')">{{ __('Delete account') }}</x-ui.button>
                </div>
            </x-ui.card>

            {{-- Same warning as a client deleting their own account (settings page), with this user's real counts. --}}
            <x-ui.modal name="confirm-admin-user-deletion" :show="$errors->has('confirmPermanent')">
                <form wire:submit="delete" class="p-6">
                    <h2 class="text-lg font-semibold text-ink">{{ __('Delete :name permanently?', ['name' => $user->name]) }}</h2>
                    <p class="mt-1 text-sm text-ink-soft">{{ __('This cannot be undone. The following is deleted right away:') }}</p>

                    <ul class="mt-3 list-disc space-y-1 rounded-lg border border-red-200 bg-red-50 py-3 pl-8 pr-4 text-sm text-red-900">
                        <li>{{ trans_choice(':count invitation, with its sections, photos, and music|:count invitations, with their sections, photos, and music', $deletion['invitations'], ['count' => Number::format($deletion['invitations'])]) }}</li>
                        <li>{{ trans_choice(':count guest, with their personal link, RSVP, and wishes|:count guests, with their personal links, RSVPs, and wishes', $deletion['guests'], ['count' => Number::format($deletion['guests'])]) }}</li>
                        <li>{{ __('Their WhatsApp campaigns, message templates, and connected WhatsApp number') }}</li>
                    </ul>

                    <p class="mt-3 text-sm text-ink-soft">{{ __('Their invitation links stop working and an active plan ends. Payment records are kept without their name or email, and the email address can sign up again.') }}</p>

                    <div class="mt-5">
                        <label for="confirm_permanent" class="flex cursor-pointer items-start gap-3 text-sm text-ink">
                            <input id="confirm_permanent" type="checkbox" wire:model="confirmPermanent" required
                                class="mt-0.5 size-4 cursor-pointer rounded border-line text-red-600 focus:ring-red-600">
                            <span>{{ __("I understand that this user's invitations and guest data are deleted permanently and cannot be recovered.") }}</span>
                        </label>
                        @error('confirmPermanent')
                            <p class="mt-1.5 text-xs font-medium text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- On phones "Cancel" sits on top, so a stray tap lands on the safe choice; 44px targets. --}}
                    <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <x-ui.button variant="secondary" type="button" class="h-11 sm:h-10" x-on:click="$dispatch('close-modal', 'confirm-admin-user-deletion')">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button variant="danger" class="h-11 sm:h-10" wire:loading.attr="disabled" wire:target="delete">{{ __('Delete permanently') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>
        @endunless
    </div>
</div>
