{{-- One large photo at a time with arrows and dots; it advances every 5 seconds until the guest picks one. --}}
<div x-data="{
        images: @js($config['images']),
        index: 0,
        zoom: false,
        timer: null,
        show(i) { this.index = (i + this.images.length) % this.images.length },
        stop() { clearInterval(this.timer) },
        init() { if (this.images.length > 1) this.timer = setInterval(() => ! this.zoom && this.show(this.index + 1), 5000) },
        destroy() { this.stop() },
    }">
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    @if (empty($config['images']))
        <p class="bc-lead">{{ __('Photos will be added soon.') }}</p>
    @else
        <div class="bc-slideshow inv-animate">
            <button type="button" class="bc-slideshow__main" x-on:click="zoom = true" aria-label="{{ __('Enlarge photo') }}">
                <img x-bind:src="images[index]" src="{{ $config['images'][0] }}" alt="" decoding="async">
            </button>
            @if (count($config['images']) > 1)
                <div class="bc-pager">
                    <button type="button" class="bc-pager__arrow" x-on:click="stop(); show(index - 1)" aria-label="{{ __('Previous photo') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                    <div class="bc-pager__dots">
                        @foreach ($config['images'] as $image)
                            <button type="button" x-on:click="stop(); show({{ $loop->index }})" x-bind:class="index === {{ $loop->index }} && 'is-active'" aria-label="{{ __('Photo :n', ['n' => $loop->iteration]) }}"></button>
                        @endforeach
                    </div>
                    <button type="button" class="bc-pager__arrow" x-on:click="stop(); show(index + 1)" aria-label="{{ __('Next photo') }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                </div>
            @endif
        </div>
        @include('designs.botanical-cream.partials.lightbox')
    @endif
</div>
