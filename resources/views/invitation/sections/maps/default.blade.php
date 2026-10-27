<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['venue']))
        <p class="mt-4 text-lg font-medium">{{ $config['venue'] }}</p>
    @endif
    @if (filled($config['address']))
        <p class="mx-auto mt-1 max-w-md text-sm inv-muted">{{ $config['address'] }}</p>
    @endif

    @if ($config['embed_url'])
        <iframe src="{{ $config['embed_url'] }}" title="{{ $config['venue'] ?: __('Map') }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
            class="mt-8 aspect-[4/3] w-full border-0 inv-rounded sm:aspect-video"></iframe>
    @endif

    @if ($config['maps_url'])
        <a href="{{ $config['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="inv-btn mt-8">
            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ __('Open Google Maps') }}
        </a>
    @endif
</div>
