<x-layouts.app area="dashboard" :title="__('My WhatsApp number')">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.whatsapp.index')" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('Back to campaigns') }}</x-ui.button>
    </x-slot:actions>

    <livewire:whats-app-connection />
</x-layouts.app>
