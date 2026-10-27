<div class="inv-container">
    <h2 class="inv-heading text-center text-4xl">{{ $config['heading'] }}</h2>

    @if (empty($config['images']))
        <p class="mt-6 text-center inv-muted">{{ __('Photos will be added soon.') }}</p>
    @else
        <div class="mt-10" x-data="{ images: @js($config['images']), index: 0, show(i) { this.index = (i + this.images.length) % this.images.length } }">
            <div class="relative overflow-hidden inv-rounded">
                <img x-bind:src="images[index]" src="{{ $config['images'][0] }}" alt="" decoding="async" class="aspect-[3/2] w-full object-cover">
                @if (count($config['images']) > 1)
                    <button type="button" x-on:click="show(index - 1)" class="absolute left-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/55 text-white" aria-label="{{ __('Previous photo') }}"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" x-on:click="show(index + 1)" class="absolute right-3 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-black/55 text-white" aria-label="{{ __('Next photo') }}"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                @endif
            </div>
            @if (count($config['images']) > 1)
                <div class="mt-3 flex justify-center gap-2">
                    @foreach ($config['images'] as $image)
                        <button type="button" x-on:click="show({{ $loop->index }})" class="h-2 w-2 rounded-full bg-current transition"
                            x-bind:class="index === {{ $loop->index }} ? 'opacity-100' : 'opacity-30'" aria-label="{{ __('Photo :n', ['n' => $loop->iteration]) }}"></button>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
