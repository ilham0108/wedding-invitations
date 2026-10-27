@props(['label', 'value', 'icon', 'href' => null])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['flex items-center gap-4 rounded-xl border border-line bg-white p-5 shadow-soft', 'transition hover:border-ink/20' => $href]) }}>
    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-sage-soft text-sage">
        <i class="{{ $icon }}" aria-hidden="true"></i>
    </span>
    <span>
        <span class="block text-sm text-muted">{{ $label }}</span>
        <span class="block text-2xl font-semibold tracking-tight text-ink">{{ $value }}</span>
    </span>
</{{ $tag }}>
