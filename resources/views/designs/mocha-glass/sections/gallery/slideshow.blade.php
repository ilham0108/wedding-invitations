{{-- Large photo with a thumbnail strip, autoplay, and a lightbox (Alpine, no extra script). --}}
<div class="mg-card mg-gallery inv-animate"
    x-data="{
        images: @js($config['images']),
        index: 0,
        zoom: false,
        timer: null,
        show(i) {
            this.index = (i + this.images.length) % this.images.length;

            // Scroll only the thumbnail strip sideways. scrollIntoView() would also scroll the page to the gallery.
            const strip = this.$refs.thumbs;
            const thumb = strip?.children[this.index];
            if (thumb) strip.scrollTo({ left: thumb.offsetLeft - strip.offsetLeft - 8, behavior: 'smooth' });
        },
        init() {
            if (this.images.length > 1) this.timer = setInterval(() => document.body.classList.contains('mg-is-open') && ! this.zoom && this.show(this.index + 1), 5000);
        },
        destroy() { clearInterval(this.timer) },
    }">
    <h2 class="mg-title">{{ $config['heading'] }}</h2>

    @if (empty($config['images']))
        <p class="mg-lead">{{ __('Photos will be added soon.') }}</p>
    @else
        <button type="button" class="mg-gallery__main" x-on:click="zoom = true" aria-label="{{ __('Enlarge photo') }}">
            <img x-bind:src="images[index]" src="{{ $config['images'][0] }}" alt="" decoding="async">
        </button>

        @if (count($config['images']) > 1)
            <div class="mg-gallery__strip">
                <button type="button" class="mg-gallery__nav" x-on:click="show(index - 1)" aria-label="{{ __('Previous photo') }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                <div class="mg-gallery__thumbs" x-ref="thumbs">
                    @foreach ($config['images'] as $image)
                        <button type="button" x-on:click="show({{ $loop->index }})" x-bind:class="index === {{ $loop->index }} && 'is-active'" aria-label="{{ __('Photo :n', ['n' => $loop->iteration]) }}">
                            <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
                <button type="button" class="mg-gallery__nav" x-on:click="show(index + 1)" aria-label="{{ __('Next photo') }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
            </div>
        @endif

        @include('designs.mocha-glass.partials.lightbox')
    @endif
</div>
