<div class="space-y-6">
    <x-ui.card :title="__('Upload songs')" :description="__('MP3 or M4A up to :size MB each. Clients pick these songs as background music for their invitation.', ['size' => $maxUploadMb])">
        <x-ui.upload-progress>
            <form wire:submit="saveUploads" class="grid gap-4 sm:grid-cols-[1fr_auto] sm:items-end">
                <x-ui.field name="files" for="upload_files" :label="__('Files')">
                    <input id="upload_files" type="file" multiple accept="audio/mpeg,audio/mp4,.mp3,.m4a" wire:model="files"
                        class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-sage-soft">
                </x-ui.field>

                <x-ui.button wire:loading.attr="disabled" wire:target="files,saveUploads" icon="fa-solid fa-upload">{{ __('Upload') }}</x-ui.button>

                <label class="flex items-center gap-2 text-sm text-ink-soft sm:col-span-2">
                    <input type="checkbox" wire:model="uploadPremium" class="rounded border-line text-sage focus:ring-sage">
                    {{ __('Mark as premium') }}
                </label>
            </form>
        </x-ui.upload-progress>
        <p class="mt-2 text-xs text-muted">{{ __('Only upload songs you have the right to share, e.g. royalty-free or licensed music.') }}</p>
        @error('files')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
        @error('files.*')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
    </x-ui.card>

    @if ($tracks->isEmpty())
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-solid fa-music" :title="__('No songs yet.')" :description="__('Upload MP3 or M4A files that clients can use as background music.')" />
        </x-ui.card>
    @else
        <x-ui.card padding="p-0">
            <ul class="divide-y divide-line">
                @foreach ($tracks as $track)
                    <li wire:key="admin-track-{{ $track->ulid }}" @class(['grid gap-3 p-4 lg:grid-cols-[minmax(0,1fr)_320px_auto] lg:items-center', 'opacity-60' => ! $track->is_active])>
                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center gap-2">
                                <input type="text" value="{{ $track->title }}" maxlength="120" aria-label="{{ __('Title') }}"
                                    x-on:change="$wire.rename('{{ $track->ulid }}', 'title', $event.target.value)"
                                    class="block w-full rounded-md border-transparent px-1 py-0.5 text-sm font-medium text-ink hover:border-line focus:border-sage focus:ring-sage">
                                @if ($track->is_premium)<x-ui.badge variant="warning">{{ __('Premium') }}</x-ui.badge>@endif
                                @unless ($track->is_active)<x-ui.badge>{{ __('Hidden') }}</x-ui.badge>@endunless
                            </div>
                            <input type="text" value="{{ $track->artist }}" maxlength="120" placeholder="{{ __('Artist') }}" aria-label="{{ __('Artist') }}"
                                x-on:change="$wire.rename('{{ $track->ulid }}', 'artist', $event.target.value)"
                                class="block w-full rounded-md border-transparent px-1 py-0.5 text-xs text-muted hover:border-line focus:border-sage focus:ring-sage">
                        </div>

                        <audio controls preload="none" src="{{ $track->url() }}" class="h-9 w-full"></audio>

                        <div class="flex items-center gap-4 text-xs">
                            <button type="button" wire:click="toggleActive('{{ $track->ulid }}')" class="text-ink-soft hover:text-ink">{{ $track->is_active ? __('Hide') : __('Show') }}</button>
                            <button type="button" wire:click="togglePremium('{{ $track->ulid }}')" class="text-ink-soft hover:text-ink">{{ $track->is_premium ? __('Make free') : __('Make premium') }}</button>
                            <button type="button" wire:click="remove('{{ $track->ulid }}')" data-confirm="{{ __('Delete this song?') }}" class="text-red-600 hover:underline" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif
</div>
