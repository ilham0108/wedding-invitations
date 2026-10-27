<x-layouts.app area="dashboard" :title="__('Welcome, :name', ['name' => $user->name])">
    <x-slot:subtitle>
        <p class="mt-1 text-sm text-muted">{{ __('This is your invitation studio.') }}</p>
    </x-slot:subtitle>

    <x-slot:actions>
        <x-ui.button :href="route('dashboard.invitations.create')" variant="accent" icon="fa-solid fa-plus">{{ __('Create Invitation') }}</x-ui.button>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat-card :label="__('Total Invitations')" :value="Number::format($stats['invitations'])" icon="fa-regular fa-envelope-open" :href="route('dashboard.invitations.index')" />
        <x-ui.stat-card :label="__('Published Invitations')" :value="Number::format($stats['published'])" icon="fa-solid fa-globe" :href="route('dashboard.invitations.index')" />
        <x-ui.stat-card :label="__('Total Guests')" :value="Number::format($stats['guests'])" icon="fa-solid fa-user-group" :href="route('dashboard.guests.index')" />
    </div>

    <x-ui.card :title="__('Recent Invitations')" class="mt-6" padding="p-0">
        @if ($recentInvitations->isEmpty())
            <x-ui.empty-state icon="fa-regular fa-envelope-open" :title="__('You haven\'t created an invitation yet.')"
                :description="__('Choose a template and have your first invitation ready in minutes.')">
                <x-slot:action>
                    <x-ui.button :href="route('dashboard.invitations.create')" variant="accent" icon="fa-solid fa-plus">{{ __('Create your first invitation') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <ul class="divide-y divide-line">
                @foreach ($recentInvitations as $invitation)
                    <li class="flex flex-wrap items-center gap-3 px-6 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium text-ink">{{ $invitation->title }}</p>
                            <p class="text-xs text-muted">{{ $invitation->template->name }} · {{ __('Updated :time', ['time' => $invitation->updated_at->diffForHumans()]) }}</p>
                        </div>
                        <x-ui.badge :variant="$invitation->status->badgeVariant()">{{ $invitation->status->label() }}</x-ui.badge>
                        <x-ui.button :href="route('dashboard.invitations.edit', $invitation)" size="sm">{{ __('Edit') }}</x-ui.button>
                        <x-ui.button :href="route('dashboard.invitations.preview', $invitation)" variant="secondary" size="sm">{{ __('Preview') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
</x-layouts.app>
