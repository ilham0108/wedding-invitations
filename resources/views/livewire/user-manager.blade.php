<div class="space-y-6">
    <x-ui.card padding="p-0">
        <div class="flex flex-wrap gap-2 border-b border-line p-4">
            <input type="search" wire:model.live.debounce.400ms="search" aria-label="{{ __('Search') }}" placeholder="{{ __('Search name or email') }}"
                class="h-9 min-w-[14rem] flex-1 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            <select wire:model.live="status" aria-label="{{ __('Filter by status') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="role" aria-label="{{ __('Filter by role') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <option value="">{{ __('All roles') }}</option>
                @foreach ($roles as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </select>
        </div>

        @if ($users->isEmpty())
            <x-ui.empty-state icon="fa-solid fa-users" :title="__('No users found.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($users as $user)
                    @php($subscription = $user->subscriptions->first())
                    <li wire:key="user-{{ $user->ulid }}">
                        <a href="{{ route('admin.users.show', $user) }}" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm hover:bg-paper">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-sage-soft font-medium text-sage">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex flex-wrap items-center gap-2 font-medium text-ink">
                                    {{ $user->name }}
                                    @foreach ($user->roles as $userRole)
                                        @if ($userRole->slug === 'super_admin')<x-ui.badge variant="accent">{{ __('Admin') }}</x-ui.badge>@endif
                                    @endforeach
                                </span>
                                <span class="block truncate text-xs text-muted">{{ $user->email }} · {{ __('Joined :date', ['date' => $user->created_at->translatedFormat('d M Y')]) }}@if ($user->last_login_at) · {{ __('Last login :time', ['time' => $user->last_login_at->diffForHumans()]) }}@endif</span>
                            </span>
                            <span class="text-xs text-ink-soft">{{ $subscription?->plan->name ?? __('Free') }}</span>
                            <span class="w-24 text-right text-xs text-muted">{{ trans_choice(':count invitation|:count invitations', $user->invitations_count) }}</span>
                            <x-ui.badge :variant="$user->isActive() ? 'success' : 'danger'">{{ $user->status->label() }}</x-ui.badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="border-t border-line px-4 py-3">{{ $users->links() }}</div>
    </x-ui.card>
</div>
