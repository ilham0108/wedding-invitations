<x-layouts.app area="admin" :title="$user->name">
    <x-slot:actions>
        <x-ui.button :href="route('admin.users.index')" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('All users') }}</x-ui.button>
    </x-slot:actions>

    <livewire:user-detail :user="$user" />
</x-layouts.app>
