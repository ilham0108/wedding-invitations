<x-layouts.app area="dashboard" :title="__('Message templates')">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.whatsapp.index')" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('Back to campaigns') }}</x-ui.button>
    </x-slot:actions>

    <livewire:whats-app-template-manager />
</x-layouts.app>
