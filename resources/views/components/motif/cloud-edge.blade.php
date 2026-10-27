{{--
    Mega mendung edge: rows of cloud bumps in shaded bands, used as an organic border between
    sections. Each band is a fill color (back to front); the last one should match the section
    it flows into. "flip" turns it upside down (clouds hanging from the top).
--}}
@props(['fills' => ['#e7ece8', '#d7e0da', '#f6f4ef'], 'flip' => false])

@php
    // One row of cloud bumps across 1440 units, starting at height $base.
    $row = function (float $offset, float $base): string {
        $path = "M0 160 L0 {$base}";
        $width = 180;

        for ($x = -$width + $offset; $x < 1440 + $width; $x += $width) {
            $a = $x + $width * 0.18;
            $b = $x + $width * 0.42;
            $c = $x + $width * 0.5;
            $d = $x + $width * 0.62;
            $e = $x + $width * 0.86;
            $f = $x + $width;
            $top = $base - 34;
            $mid = $base - 18;
            $path .= " L{$x} {$base} C{$a} {$top} {$b} {$top} {$c} {$mid} C{$d} {$top} {$e} {$mid} {$f} {$base}";
        }

        return $path.' L1440 160 Z';
    };
    $count = count($fills);
@endphp

<svg {{ $attributes->merge(['viewBox' => '0 0 1440 160', 'preserveAspectRatio' => 'xMidYMax slice', 'aria-hidden' => 'true']) }}
    xmlns="http://www.w3.org/2000/svg" @if ($flip) style="transform: scaleY(-1)" @endif>
    @foreach ($fills as $index => $fill)
        <path d="{{ $row(($index * 70) % 180, 70 + $index * (70 / max(1, $count - 1))) }}" fill="{{ $fill }}" />
    @endforeach
</svg>
