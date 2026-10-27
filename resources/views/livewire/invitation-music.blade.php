<div class="space-y-6"
    x-data="{
        audio: new Audio(),
        playing: null,
        listen(url) {
            if (this.playing === url) { this.audio.pause(); this.playing = null; return; }
            this.audio.src = url;
            this.audio.play().then(() => this.playing = url).catch(() => this.playing = null);
        },
        init() { this.audio.addEventListener('ended', () => this.playing = null) },
        destroy() { this.audio.pause() },
    }">
    <label class="flex items-start justify-between gap-3">
        <span>
            <span class="block text-sm font-medium text-ink">{{ __('Background music') }}</span>
            <span class="mt-0.5 block text-xs text-muted">{{ __('Starts when the guest opens the invitation; browsers do not allow sound before the first tap.') }}</span>
        </span>
        <input type="checkbox" wire:model.live="music.enabled" class="mt-1 rounded border-line text-sage focus:ring-sage">
    </label>

    <div>
        <p class="mb-2 text-sm font-medium text-ink">{{ __('Music library') }}</p>
        @if ($tracks->isEmpty())
            <p class="rounded-lg bg-paper p-3 text-xs text-muted">{{ __('No songs in the library yet.') }}</p>
        @else
            <ul class="space-y-1.5">
                @foreach ($tracks as $track)
                    @php
                        $locked = $track->is_premium && ! $canUsePremium;
                        $selected = $music['source'] === 'library' && $music['track'] === $track->ulid;
                    @endphp
                    <li wire:key="music-track-{{ $track->ulid }}" @class(['flex items-center gap-2 rounded-lg border px-2 py-2', 'border-ink bg-paper' => $selected, 'border-line' => ! $selected])>
                        <button type="button" x-on:click="listen(@js($track->url()))" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-paper text-ink-soft hover:text-ink"
                            x-bind:aria-label="playing === @js($track->url()) ? @js(__('Stop')) : @js(__('Listen'))">
                            <i class="fa-solid text-xs" x-bind:class="playing === @js($track->url()) ? 'fa-stop' : 'fa-play'" aria-hidden="true"></i>
                        </button>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink">{{ $track->title }}</span>
                            @if (filled($track->artist))<span class="block truncate text-xs text-muted">{{ $track->artist }}</span>@endif
                        </span>
                        @if ($selected)
                            <i class="fa-solid fa-check text-sage" aria-label="{{ __('Selected') }}"></i>
                        @elseif ($locked)
                            <span class="flex items-center gap-1 text-xs text-muted"><i class="fa-solid fa-lock" aria-hidden="true"></i>{{ __('Premium') }}</span>
                        @else
                            <button type="button" wire:click="chooseTrack('{{ $track->ulid }}')" class="rounded-md px-2 py-1 text-xs font-medium text-ink-soft hover:bg-paper hover:text-ink">{{ __('Use') }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
            @unless ($canUsePremium)
                @if ($tracks->contains('is_premium', true))
                    <p class="mt-2 text-xs text-muted">{{ __('Premium songs are part of a higher plan.') }} <a href="{{ route('dashboard.subscription.index') }}" class="font-medium text-sage hover:underline">{{ __('See plans') }}</a></p>
                @endif
            @endunless
        @endif
    </div>

    <div>
        <p class="mb-2 text-sm font-medium text-ink">{{ __('Your own song') }}</p>
        @if ($canUpload)
            @if ($upload)
                @php($uploadSelected = $music['source'] === 'upload')
                <div @class(['mb-2 flex items-center gap-2 rounded-lg border px-2 py-2', 'border-ink bg-paper' => $uploadSelected, 'border-line' => ! $uploadSelected])>
                    <button type="button" x-on:click="listen(@js($upload->url()))" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-paper text-ink-soft hover:text-ink" aria-label="{{ __('Listen') }}">
                        <i class="fa-solid text-xs" x-bind:class="playing === @js($upload->url()) ? 'fa-stop' : 'fa-play'" aria-hidden="true"></i>
                    </button>
                    <span class="min-w-0 flex-1 truncate text-sm text-ink">{{ $upload->original_name }}</span>
                    @if ($uploadSelected)
                        <i class="fa-solid fa-check text-sage" aria-label="{{ __('Selected') }}"></i>
                    @else
                        <button type="button" wire:click="useUpload" class="rounded-md px-2 py-1 text-xs font-medium text-ink-soft hover:bg-paper hover:text-ink">{{ __('Use') }}</button>
                    @endif
                </div>
            @endif
            <x-ui.upload-progress>
                <input type="file" accept="audio/mpeg,audio/mp4,.mp3,.m4a" wire:model="song" aria-label="{{ __('Upload a song') }}"
                    class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-sage-soft">
            </x-ui.upload-progress>
            <p class="mt-1 text-xs text-muted">{{ __('MP3 or M4A up to :size MB. A new upload replaces your previous song.', ['size' => $maxUploadMb]) }}</p>
            @error('song')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
        @else
            <p class="rounded-lg bg-paper p-3 text-xs text-muted">
                <i class="fa-solid fa-lock mr-1" aria-hidden="true"></i>{{ __('Uploading your own song is part of a higher plan.') }}
                <a href="{{ route('dashboard.subscription.index') }}" class="font-medium text-sage hover:underline">{{ __('See plans') }}</a>
            </p>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="music-start-at" class="block text-sm font-medium text-ink">{{ __('Start at (seconds)') }}</label>
            <input id="music-start-at" type="number" min="0" max="{{ $maxStartAt }}" wire:model.live.debounce.700ms="music.start_at"
                class="mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
        </div>
        <label class="flex items-center gap-2 self-end pb-2 text-sm text-ink-soft">
            <input type="checkbox" wire:model.live="music.loop" class="rounded border-line text-sage focus:ring-sage">
            {{ __('Repeat') }}
        </label>
    </div>
</div>
