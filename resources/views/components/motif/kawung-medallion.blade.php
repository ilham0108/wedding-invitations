{{--
    Kawung medallion: eight kawung petals around a four-petal heart, inside dotted and scalloped
    rings. Hand-drawn geometry; color from currentColor (lines), the "accent" attribute (petal hearts)
    and "tint" (how strongly the hearts are filled).
--}}
@props(['accent' => '#c8634b', 'tint' => 0.3])

<svg {{ $attributes->merge(['viewBox' => '-100 -100 200 200', 'fill' => 'none', 'aria-hidden' => 'true']) }} xmlns="http://www.w3.org/2000/svg">
    <g stroke="currentColor" stroke-width="0.8">
        <circle r="97" />
        <circle r="92" stroke-dasharray="1 3.2" stroke-linecap="round" />
        @for ($i = 0; $i < 32; $i++)
            <circle cx="0" cy="-86" r="4.2" transform="rotate({{ $i * 11.25 }})" />
        @endfor
        <circle r="78" />

        {{-- Eight large kawung petals with a filled heart. --}}
        @for ($i = 0; $i < 8; $i++)
            <g transform="rotate({{ $i * 45 }})">
                <ellipse cx="0" cy="-49" rx="13" ry="25" />
                <ellipse cx="0" cy="-49" rx="6.5" ry="15" fill="{{ $accent }}" fill-opacity="{{ $tint }}" stroke="none" />
                <circle cx="0" cy="-49" r="1.6" fill="currentColor" stroke="none" />
            </g>
            {{-- Small petals between the large ones. --}}
            <ellipse cx="0" cy="-60" rx="4" ry="9" transform="rotate({{ $i * 45 + 22.5 }})" />
        @endfor

        <circle r="21" />
        @for ($i = 0; $i < 16; $i++)
            <circle cx="0" cy="-26" r="1.2" fill="currentColor" stroke="none" transform="rotate({{ $i * 22.5 }})" />
        @endfor

        {{-- Four-petal kawung heart. --}}
        @for ($i = 0; $i < 4; $i++)
            <ellipse cx="0" cy="-9" rx="4.5" ry="8.5" transform="rotate({{ $i * 90 + 45 }})" fill="{{ $accent }}" fill-opacity="{{ $tint * 0.8 }}" />
        @endfor
        <circle r="2.2" fill="currentColor" stroke="none" />
    </g>
</svg>
