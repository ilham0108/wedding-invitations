{{-- "Arina & Bagas" on three lines, as in the original design. --}}
@php($parts = array_values(array_filter(array_map('trim', explode('&', (string) $names)))))
@if (count($parts) === 2)
    <span>{{ $parts[0] }}</span><br>&amp;<br><span>{{ $parts[1] }}</span>
@else
    {{ $names }}
@endif
