{{-- Names in script; the "&" between them is set apart in a lighter style. The cover's "title_layout" ($layout) can set other lines. --}}
@php($parts = preg_split('/\s*&\s*/u', (string) $names, 2))
@if (\App\Support\CoverTitle::layout($layout ?? null) !== null)
    @include('invitation.partials.cover-title', ['layout' => \App\Support\CoverTitle::layout($layout), 'ampClass' => 'bc-names__amp'])
@elseif (count($parts) === 2)
    <span class="bc-names__line">{{ $parts[0] }}</span>
    <span class="bc-names__line"><span class="bc-names__amp">&amp;</span>{{ $parts[1] }}</span>
@else
    <span class="bc-names__line">{{ $names }}</span>
@endif
