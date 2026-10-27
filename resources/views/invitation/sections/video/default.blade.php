<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>

    @if ($config['embed_url'])
        {{-- Click-to-load: no third-party player is fetched until the guest asks for it. --}}
        <div x-data="{ playing: false }" class="relative mt-10 aspect-video overflow-hidden inv-rounded" style="background: var(--inv-surface);">
            <template x-if="playing">
                <iframe src="{{ $config['embed_url'] }}" class="absolute inset-0 h-full w-full" title="{{ $config['heading'] }}"
                    allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen></iframe>
            </template>
            <button type="button" x-show="! playing" x-on:click="playing = true" class="group absolute inset-0 flex items-center justify-center" aria-label="{{ __('Play video') }}">
                @if ($config['thumbnail'])
                    <img src="{{ $config['thumbnail'] }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">
                @endif
                <span class="relative flex h-16 w-16 items-center justify-center rounded-full bg-black/60 text-xl text-white transition group-hover:scale-105">
                    <i class="fa-solid fa-play" aria-hidden="true"></i>
                </span>
            </button>
        </div>
    @else
        <p class="mt-6 inv-muted">{{ __('Video coming soon.') }}</p>
    @endif
</div>
