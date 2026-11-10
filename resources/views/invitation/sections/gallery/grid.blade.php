<div class="inv-container">
    <h2 class="inv-heading text-center text-4xl" @animate('heading')>{{ $config['heading'] }}</h2>

    @if (empty($config['images']))
        <p class="mt-6 text-center inv-muted" @animate('images')>{{ __('Photos will be added soon.') }}</p>
    @else
        <div class="mt-10 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($config['images'] as $image)
                <img src="{{ $image }}" alt="" loading="lazy" decoding="async" class="aspect-square w-full object-cover inv-rounded" @animate('images', $loop->index)>
            @endforeach
        </div>
    @endif
</div>
