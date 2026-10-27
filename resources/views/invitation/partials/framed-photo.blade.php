{{--
    A portrait with the section's photo shape and optional frame (couple "photo_shape", "photo_frame", "photo_frame_inset").
    Without either, the design's own <img> is kept: $imageClass / $imageStyle. With a frame, the frame image sets the
    size and the photo fills its opening, inset by photo_frame_inset percent; $wrapperClass sizes the block.
    $focus is the person's "photo_focus": the part of the photo that stays visible when it is cropped.
--}}
@php
    $shape = $config['photo_shape'] ?? 'default';
    $frame = $config['photo_frame'] ?? null;
    $position = \App\Support\PhotoStyle::image($focus ?? null);
@endphp

@if ($frame === null && $shape === 'default')
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async" class="{{ $imageClass }}" @if (filled($imageStyle ?? null) || $position !== '') style="{{ trim(($imageStyle ?? '').' '.$position) }}" @endif>
@else
    <div @class(['inv-photo', 'inv-photo--'.($shape === 'default' ? 'oval' : $shape), 'inv-photo--framed' => $frame !== null, $wrapperClass])
        style="--inv-photo-inset: {{ $config['photo_frame_inset'] ?? 12 }}%">
        <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" decoding="async" class="inv-photo__image" @if ($position !== '') style="{{ $position }}" @endif>
        @if ($frame !== null)
            <img src="{{ $frame }}" alt="" aria-hidden="true" loading="lazy" decoding="async" class="inv-photo__frame">
        @endif
    </div>
@endif
