<x-layouts.app area="dashboard" :title="__('Guests')">
    @if ($selected === null)
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-solid fa-user-group" :title="__('Start building your guest list.')"
                :description="__('Create an invitation first, then add the people you want to invite.')">
                <x-slot:action>
                    <x-ui.button :href="route('dashboard.invitations.create')" variant="accent" icon="fa-solid fa-plus">{{ __('Create Invitation') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-slot:actions>
            <form method="GET" action="{{ route('dashboard.guests.index') }}" class="flex items-center gap-2">
                <label for="invitation" class="text-sm text-muted">{{ __('Invitation') }}</label>
                <select id="invitation" name="invitation" onchange="this.form.submit()" class="rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    @foreach ($invitations as $invitation)
                        <option value="{{ $invitation->ulid }}" @selected($invitation->ulid === $selected->ulid)>{{ $invitation->title }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>

        <div class="space-y-6">
            <livewire:guest-import :invitation-ulid="$selected->ulid" :key="'import-'.$selected->ulid" />
            <livewire:guest-table :invitation-ulid="$selected->ulid" :key="'table-'.$selected->ulid" />
        </div>
    @endif
</x-layouts.app>
