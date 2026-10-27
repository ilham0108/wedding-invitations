{{--
    Ornaments of one section ($ornaments, cleaned by App\Services\Invitation\Ornaments), on the "back"
    layer (behind the section content) or the "front" layer. Three boxes per ornament so the effects
    never fight over "transform": the outer box places and mirrors it, the middle one plays the
    entrance (when the section scrolls into view; invitation.js adds .is-shown), and the image sways
    or floats around its anchor. The parent element must be positioned.
--}}
@php
    // Where the anchor sits inside the image (percent of its box): x, y.
    $anchorPoint = [
        'top-left' => [0, 0], 'top' => [50, 0], 'top-right' => [100, 0],
        'left' => [0, 50], 'center' => [50, 50], 'right' => [100, 50],
        'bottom-left' => [0, 100], 'bottom' => [50, 100], 'bottom-right' => [100, 100],
    ];
    // Starting transform of each entrance (it ends at "none").
    $enterFrom = [
        'fade' => 'none',
        'fade-up' => 'translateY(40px)',
        'fade-down' => 'translateY(-40px)',
        'fade-left' => 'translateX(40px)',
        'fade-right' => 'translateX(-40px)',
        'zoom-in' => 'scale(0.6)',
        'zoom-in-up' => 'translateY(40px) scale(0.6)',
        'zoom-in-left' => 'translateX(40px) scale(0.6)',
        'zoom-in-right' => 'translateX(-40px) scale(0.6)',
        'zoom-out' => 'scale(1.3)',
    ];
    $layers = collect($ornaments ?? [])->groupBy('layer');
@endphp

@foreach (['back', 'front'] as $layer)
    @if ($layers->has($layer))
        <div class="inv-ornaments inv-ornaments--{{ $layer }}" data-inv-ornaments aria-hidden="true">
            @foreach ($layers[$layer] as $ornament)
                @php
                    [$anchorX, $anchorY] = $anchorPoint[$ornament['anchor']];
                    // A mirrored box mirrors what is inside it: flip the pivot, and the horizontal entrance back.
                    $originX = $ornament['flip'] ? 100 - $anchorX : $anchorX;
                    $enter = $ornament['enter'];
                    if ($ornament['flip']) {
                        $enter = strtr($enter, ['left' => 'right', 'right' => 'left']);
                    }
                @endphp
                <div @class([
                    'inv-ornament',
                    'inv-ornament--enter' => $ornament['enter'] !== 'none',
                    'inv-ornament--'.$ornament['motion'],
                    'inv-ornament--flip' => $ornament['flip'],
                ]) style="left: {{ $ornament['x'] }}%; top: {{ $ornament['y'] }}%; width: {{ $ornament['width'] }}%; translate: -{{ $anchorX }}% -{{ $anchorY }}%; --inv-ornament-origin: {{ $originX }}% {{ $anchorY }}%; --inv-ornament-from: {{ $enterFrom[$enter] ?? 'none' }}; --inv-ornament-duration: {{ $ornament['duration'] }}s; --inv-ornament-delay: {{ $ornament['delay'] }}s; --inv-ornament-phase: {{ $loop->index * -1.3 }}s;">
                    <div class="inv-ornament__enter">
                        <img src="{{ $ornament['image'] }}" alt="" loading="lazy" decoding="async" class="inv-ornament__img">
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endforeach
