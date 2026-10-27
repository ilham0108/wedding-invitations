<div class="inv-container">
    <h2 class="inv-heading text-center text-4xl">{{ $config['heading'] }}</h2>

    @if (empty($config['images']))
        <p class="mt-6 text-center inv-muted">{{ __('Photos will be added soon.') }}</p>
    @else
        <div class="mt-10 columns-2 gap-3 sm:columns-3">
            @foreach ($config['images'] as $image)
                <img src="{{ $image }}" alt="" loading="lazy" decoding="async" class="mb-3 w-full break-inside-avoid inv-rounded">
            @endforeach
        </div>
    @endif
</div>
