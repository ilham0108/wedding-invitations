<x-layouts.app area="dashboard" :title="$campaign->name">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.whatsapp.index', ['invitation' => $campaign->invitation->ulid])" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('Back to campaigns') }}</x-ui.button>
    </x-slot:actions>

    <livewire:whats-app-campaign-detail :campaign-ulid="$campaign->ulid" />
</x-layouts.app>
