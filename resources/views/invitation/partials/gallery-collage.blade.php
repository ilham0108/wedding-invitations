{{--
    Gallery layout "collage": the photos as instant prints laid on the page, in a repeating pattern of two tilted side
    by side and one across under them, overlapping a little (invitation.css "Gallery collage"). Shared by every design.
    $zoomable: each print is a button that opens the design's lightbox (Alpine "index" and "zoom" around it).
    $limit: prints shown until "See all" (Alpine "all"), or null for all of them.
--}}
<div class="inv-collage">
    @foreach ($config['images'] as $image)
        @php
            $classes = [
                'inv-collage__item inv-collage__item--'.($loop->index % 3),
                // A last print without a partner goes across, like the third of a set.
                'inv-collage__item--alone' => $loop->last && $loop->index % 3 === 0 && ! $loop->first,
            ];
            $hidden = isset($limit) && $loop->index >= $limit;
        @endphp
        @if ($zoomable ?? false)
            <button type="button" @class($classes) @animate('images', $loop->index) x-on:click="index = {{ $loop->index }}; zoom = true" aria-label="{{ __('Enlarge photo') }}"
                @if ($hidden) x-show="all" x-cloak @endif>
                <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
            </button>
        @else
            <figure @class($classes) @animate('images', $loop->index)>
                <img src="{{ $image }}" alt="" loading="lazy" decoding="async">
            </figure>
        @endif
    @endforeach
</div>
