{{-- "Arina & Bagas" on three lines, as in the original design, unless the cover's "title_layout" ($layout) says otherwise. --}}
@php($parts = array_values(array_filter(array_map('trim', explode('&', (string) $names)))))
@if (\App\Support\CoverTitle::layout($layout ?? null) !== null)
    @include('invitation.partials.cover-title', ['layout' => \App\Support\CoverTitle::layout($layout)])
@elseif (count($parts) === 2)
    <span>{{ $parts[0] }}</span><br>&amp;<br><span>{{ $parts[1] }}</span>
@else
    {{ $names }}
@endif
