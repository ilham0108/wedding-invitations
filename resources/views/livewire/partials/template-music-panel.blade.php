{{-- Template builder "Music" panel: the template's default song from the music library. --}}
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
    <div>
        <h2 class="font-display text-xl text-ink">{{ __('Default music') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ __('Plays on the template preview and is set on every new invitation made from this template. Clients can change or turn it off. Premium songs are only given to clients whose plan includes them.') }}</p>
    </div>

    <div>
        <p class="mb-2 text-sm font-medium text-ink">{{ __('Selected song') }}</p>
        @if ($selectedTrack)
            <div class="flex items-center gap-2 rounded-lg border border-ink bg-paper px-2 py-2">
                <button type="button" x-on:click="listen(@js($selectedTrack->url()))" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-ink-soft hover:text-ink"
                    x-bind:aria-label="playing === @js($selectedTrack->url()) ? @js(__('Stop')) : @js(__('Listen'))">
                    <i class="fa-solid text-xs" x-bind:class="playing === @js($selectedTrack->url()) ? 'fa-stop' : 'fa-play'" aria-hidden="true"></i>
                </button>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm text-ink">{{ $selectedTrack->title }}</span>
                    @if (filled($selectedTrack->artist))<span class="block truncate text-xs text-muted">{{ $selectedTrack->artist }}</span>@endif
                </span>
                @if ($selectedTrack->is_premium)
                    <x-ui.badge variant="accent">{{ __('Premium') }}</x-ui.badge>
                @endif
                <button type="button" wire:click="removeMusic" class="rounded-md px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50">{{ __('Remove') }}</button>
            </div>
            @unless ($selectedTrack->is_active)
                <p class="mt-1 text-xs text-amber-700">{{ __('This song is hidden in the music library, so new invitations start without music.') }}</p>
            @endunless
        @else
            <p class="rounded-lg bg-paper p-3 text-xs text-muted">{{ __('No default music. New invitations start without music.') }}</p>
        @endif
    </div>

    @if ($selectedTrack)
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="template-music-start-at" class="block text-sm font-medium text-ink">{{ __('Start at (seconds)') }}</label>
                <input id="template-music-start-at" type="number" min="0" max="{{ $maxStartAt }}" wire:model.live.debounce.700ms="music.start_at"
                    class="mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            </div>
            <label class="flex items-center gap-2 self-end pb-2 text-sm text-ink-soft">
                <input type="checkbox" wire:model.live="music.loop" class="rounded border-line text-sage focus:ring-sage">
                {{ __('Repeat') }}
            </label>
        </div>
    @endif

    <div>
        <p class="mb-2 text-sm font-medium text-ink">{{ __('Music library') }}</p>
        @if ($musicTracks->isEmpty())
            <p class="rounded-lg bg-paper p-3 text-xs text-muted">
                {{ __('No songs in the library yet.') }}
                <a href="{{ route('admin.music.index') }}" class="font-medium text-sage hover:underline">{{ __('Upload songs') }}</a>
            </p>
        @else
            <ul class="space-y-1.5">
                @foreach ($musicTracks as $track)
                    @php($selected = $music['track'] === $track->ulid)
                    <li wire:key="template-music-{{ $track->ulid }}" @class(['flex items-center gap-2 rounded-lg border px-2 py-2', 'border-ink bg-paper' => $selected, 'border-line' => ! $selected])>
                        <button type="button" x-on:click="listen(@js($track->url()))" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-paper text-ink-soft hover:text-ink"
                            x-bind:aria-label="playing === @js($track->url()) ? @js(__('Stop')) : @js(__('Listen'))">
                            <i class="fa-solid text-xs" x-bind:class="playing === @js($track->url()) ? 'fa-stop' : 'fa-play'" aria-hidden="true"></i>
                        </button>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-ink">{{ $track->title }}</span>
                            @if (filled($track->artist))<span class="block truncate text-xs text-muted">{{ $track->artist }}</span>@endif
                        </span>
                        @if ($track->is_premium)
                            <span class="flex items-center gap-1 text-xs text-muted"><i class="fa-solid fa-crown" aria-hidden="true"></i>{{ __('Premium') }}</span>
                        @endif
                        @if ($selected)
                            <i class="fa-solid fa-check text-sage" aria-label="{{ __('Selected') }}"></i>
                        @else
                            <button type="button" wire:click="chooseMusic('{{ $track->ulid }}')" class="rounded-md px-2 py-1 text-xs font-medium text-ink-soft hover:bg-paper hover:text-ink">{{ __('Use') }}</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <p class="text-xs text-muted">{{ __('In the preview, music starts after the first tap or click, as browsers require.') }}</p>
</div>
