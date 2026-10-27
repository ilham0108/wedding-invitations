{{-- Names in script; the "&" between them is set apart in a lighter style. --}}
@php($parts = preg_split('/\s*&\s*/u', (string) $names, 2))
@if (count($parts) === 2)
    <span class="bc-names__line">{{ $parts[0] }}</span>
    <span class="bc-names__line"><span class="bc-names__amp">&amp;</span>{{ $parts[1] }}</span>
@else
    <span class="bc-names__line">{{ $names }}</span>
@endif
