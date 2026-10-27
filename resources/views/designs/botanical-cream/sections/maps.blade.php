{{-- Map card with the venue below it. The embed comes from the coordinates or the Maps URL (InvitationRenderer). --}}
<div>
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    <div class="bc-map inv-animate">
        @if ($config['embed_url'])
            <iframe src="{{ $config['embed_url'] }}" title="{{ __('Map') }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
        @endif
        <div class="bc-map__body">
            @if (filled($config['venue']))
                <h3>{{ $config['venue'] }}</h3>
            @endif
            @if (filled($config['address']))
                <p>{{ $config['address'] }}</p>
            @endif
            @if ($config['maps_url'])
                <a class="bc-btn" href="{{ $config['maps_url'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ __('Open in Google Maps') }}</a>
            @endif
        </div>
    </div>
</div>
