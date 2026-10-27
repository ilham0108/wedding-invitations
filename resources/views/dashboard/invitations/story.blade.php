<x-layouts.app area="dashboard" :title="__('Instagram Story')">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.invitations.edit', $invitation)" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('Back to editor') }}</x-ui.button>
    </x-slot:actions>

    {{-- The theme's fonts, so the canvas can draw with them. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="{{ $fontStylesheetUrl }}">
    @vite('resources/js/story.js')

    <livewire:invitation-story :invitation-ulid="$invitation->ulid" />
</x-layouts.app>
