<div class="mg-card mg-gallery inv-animate" x-data="{ images: @js($config['images']), index: 0, zoom: false }">
    <h2 class="mg-title">{{ $config['heading'] }}</h2>

    @if (empty($config['images']))
        <p class="mg-lead">{{ __('Photos will be added soon.') }}</p>
    @else
        <div class="mg-gallery__grid">
            @foreach ($config['images'] as $image)
                <button type="button" x-on:click="index = {{ $loop->index }}; zoom = true" aria-label="{{ __('Enlarge photo') }}">
                    <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
                </button>
            @endforeach
        </div>
        @include('designs.mocha-glass.partials.lightbox')
    @endif
</div>
