<x-layouts.app area="dashboard" :title="__('RSVP')">
    @if ($selected === null)
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-regular fa-circle-check" :title="__('No RSVP yet.')"
                :description="__('Create and publish an invitation to start collecting responses.')">
                <x-slot:action>
                    <x-ui.button :href="route('dashboard.invitations.create')" variant="accent" icon="fa-solid fa-plus">{{ __('Create Invitation') }}</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        </x-ui.card>
    @else
        <x-slot:actions>
            <form method="GET" action="{{ route('dashboard.rsvp.index') }}" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label for="invitation" class="text-sm text-muted">{{ __('Invitation') }}</label>
                <select id="invitation" name="invitation" onchange="this.form.submit()" class="rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    @foreach ($invitations as $invitation)
                        <option value="{{ $invitation->ulid }}" @selected($invitation->ulid === $selected->ulid)>{{ $invitation->title }}</option>
                    @endforeach
                </select>
            </form>
        </x-slot:actions>

        <nav class="mb-6 flex gap-1 border-b border-line" aria-label="{{ __('RSVP sections') }}">
            @foreach (['responses' => __('Responses'), 'guestbook' => __('Guestbook')] as $key => $label)
                <a href="{{ route('dashboard.rsvp.index', ['invitation' => $selected->ulid, 'tab' => $key]) }}"
                    @class(['-mb-px border-b-2 px-4 py-2 text-sm font-medium', 'border-ink text-ink' => $tab === $key, 'border-transparent text-muted hover:text-ink' => $tab !== $key])
                    @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($tab === 'guestbook')
            <livewire:guestbook-moderation :invitation-ulid="$selected->ulid" :key="'guestbook-'.$selected->ulid" />
        @else
            <livewire:rsvp-dashboard :invitation-ulid="$selected->ulid" :key="'rsvp-'.$selected->ulid" />
        @endif
    @endif
</x-layouts.app>
