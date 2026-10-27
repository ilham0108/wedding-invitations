<x-layouts.app area="dashboard" :title="__('New WhatsApp campaign')">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.whatsapp.index', ['invitation' => $selected->ulid])" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('Back to campaigns') }}</x-ui.button>
    </x-slot:actions>

    <p class="mb-6 text-sm text-muted">{{ __('Invitation') }}: <span class="font-medium text-ink">{{ $selected->title }}</span></p>

    <livewire:whats-app-campaign-form :invitation-ulid="$selected->ulid" :preselected="$preselected" />
</x-layouts.app>
