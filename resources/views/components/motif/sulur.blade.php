{{--
    Sulur / lung-lungan: a curling batik vine with leaves, buds and spiral tendrils, drawn from a
    bottom-left corner. Flip it with CSS (-scale-x-100 / -scale-y-100) for other corners.
    Lines use currentColor; leaves and buds are tinted with "accent".
--}}
@props(['accent' => '#c8634b'])

<svg {{ $attributes->merge(['viewBox' => '0 0 220 220', 'fill' => 'none', 'aria-hidden' => 'true']) }} xmlns="http://www.w3.org/2000/svg">
    <g stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
        {{-- Main stem with its end curled into a spiral. --}}
        <path d="M4 216 C30 180 52 150 86 132 C120 114 150 112 168 92 C184 74 178 50 160 46 C146 43 138 56 146 66 C152 73 162 68 160 60" />
        {{-- Side branches. --}}
        <path d="M54 158 C44 132 50 108 70 94 C84 84 98 92 94 104 C91 112 82 110 82 104" />
        <path d="M118 120 C128 146 152 156 172 148 C186 142 186 126 174 124 C166 123 164 132 170 135" />
        <path d="M26 188 C14 176 12 160 22 150" />

        {{-- Leaves along the stem. --}}
        @foreach ([[38, 172, -35], [70, 142, -20], [100, 124, -10], [136, 108, 15], [62, 118, -70], [150, 150, 40]] as [$x, $y, $angle])
            <g transform="translate({{ $x }} {{ $y }}) rotate({{ $angle }})">
                <path d="M0 0 C6 -14 22 -16 30 -10 C22 -2 10 4 0 0 Z" fill="{{ $accent }}" fill-opacity="0.22" />
                <path d="M2 -1 C10 -6 18 -9 26 -10" stroke-width="0.9" />
            </g>
        @endforeach
    </g>

    {{-- Buds and dots. --}}
    <g fill="{{ $accent }}">
        <circle cx="160" cy="60" r="3.2" fill-opacity="0.6" />
        <circle cx="82" cy="104" r="2.6" fill-opacity="0.6" />
        <circle cx="170" cy="135" r="2.6" fill-opacity="0.6" />
    </g>
    <g fill="currentColor">
        <circle cx="186" cy="38" r="1.6" />
        <circle cx="194" cy="52" r="1.2" />
        <circle cx="106" cy="86" r="1.4" />
        <circle cx="196" cy="118" r="1.4" />
    </g>
</svg>
