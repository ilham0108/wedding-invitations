{{-- Two columns of photos at their own proportions; six photos show until "See all". --}}
@php($limit = 6)
<div x-data="{ images: @js($config['images']), index: 0, zoom: false, all: false }">
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    @if (empty($config['images']))
        <p class="bc-lead">{{ __('Photos will be added soon.') }}</p>
    @else
        <div class="bc-gallery-masonry inv-animate">
            @foreach ($config['images'] as $image)
                <button type="button" x-on:click="index = {{ $loop->index }}; zoom = true" aria-label="{{ __('Enlarge photo') }}"
                    @if ($loop->index >= $limit) x-show="all" x-cloak @endif>
                    <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
                </button>
            @endforeach
        </div>
        @include('designs.botanical-cream.partials.gallery-more')
        @include('designs.botanical-cream.partials.lightbox')
    @endif
</div>
