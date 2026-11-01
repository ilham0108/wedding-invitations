{{--
    Ornaments of one section ($ornaments, cleaned by App\Services\Invitation\Ornaments), on the "back"
    layer (behind the section content) or the "front" layer. Three boxes per ornament so the effects
    never fight over "transform": the outer box places and mirrors it, the middle one plays the
    entrance (when the section scrolls into view; invitation.js adds .is-shown), and the image sways
    or floats around its anchor. The parent element must be positioned.

    $owner (section key) and $list ("ornaments" | "desktop_ornaments") plus each ornament's list
    index let the template builder find an ornament in its preview to move or restyle it.
--}}
@php
    $ornamentStyles = app(\App\Services\Invitation\Ornaments::class);
    $layers = collect($ornaments ?? [])->groupBy('layer', preserveKeys: true);
@endphp

@foreach (['back', 'front'] as $layer)
    @if ($layers->has($layer))
        <div class="inv-ornaments inv-ornaments--{{ $layer }}" data-inv-ornaments
            data-inv-ornament-owner="{{ $owner ?? ($section['key'] ?? '') }}" data-inv-ornament-list="{{ $list ?? 'ornaments' }}" aria-hidden="true">
            @foreach ($layers[$layer] as $index => $ornament)
                <div class="{{ $ornamentStyles->classes($ornament) }}" data-inv-ornament-index="{{ $index }}" style="{{ $ornamentStyles->style($ornament, $loop->index) }}">
                    <div class="inv-ornament__enter">
                        <img src="{{ $ornament['image'] }}" alt="" loading="lazy" decoding="async" class="inv-ornament__img">
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endforeach
