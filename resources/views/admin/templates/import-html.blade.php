<x-layouts.app area="admin" :title="__('Import HTML template')">
    <x-slot:subtitle>
        <p class="mt-1 text-sm text-muted">{{ __('Nothing is saved until the package passes the check.') }}</p>
    </x-slot:subtitle>
    <x-slot:actions>
        <x-ui.button variant="secondary" :href="route('admin.templates.index')" icon="fa-solid fa-arrow-left">{{ __('Back to templates') }}</x-ui.button>
    </x-slot:actions>

    <livewire:html-design-import />
</x-layouts.app>
