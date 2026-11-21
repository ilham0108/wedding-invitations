{{--
    The cover names in the layout chosen in the cover's "title_layout" (App\Support\CoverTitle), for every design.
    "line": as typed. "stacked" and "cascade": the two names and the "&" on three lines; "cascade" steps them from
    left to right ("Raka / & / Sinta"). A title without "&" stays as typed. $ampClass: the design's own "&" style.
--}}
@php($parts = $layout === 'line' ? null : \App\Support\CoverTitle::parts($names))
@if ($parts === null)
    {{ $names }}
@else
    <span class="inv-title-lines inv-title-lines--{{ $layout }}">
        <span class="inv-title-line">{{ $parts[0] }}</span>
        <span @class(['inv-title-line inv-title-amp', $ampClass ?? null])>&amp;</span>
        <span class="inv-title-line">{{ $parts[1] }}</span>
    </span>
@endif
