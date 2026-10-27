{{--
    Mega mendung (Cirebon cloud motif), drawn from scratch: one cloud outline repeated
    smaller and smaller around the same inner point, so the bands nest evenly like the batik.
    "filled" shades the bands darker towards the middle, as in the cloth.
    Decorative only; color comes from currentColor, size from the class.
--}}
@props(['filled' => false])

<svg {{ $attributes->merge(['viewBox' => '0 0 240 150', 'fill' => 'none', 'aria-hidden' => 'true']) }} xmlns="http://www.w3.org/2000/svg">
    <g stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        @foreach ([1, 0.82, 0.64, 0.46, 0.28] as $index => $scale)
            <path transform="translate(122 112) scale({{ $scale }}) translate(-122 -112)"
                @if ($filled) fill="currentColor" fill-opacity="{{ 0.08 + $index * 0.07 }}" @endif
                d="M14 138 C6 112 28 92 52 104 C48 70 84 50 110 70 C118 38 160 28 182 56 C206 44 236 66 228 96 C244 104 240 136 222 138 Z" />
        @endforeach
    </g>
</svg>
