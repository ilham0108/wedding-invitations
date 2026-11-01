{{-- One figure: a small label over the number. Flat (no shadow), so it reads as a summary, not as content. --}}
@props(['label', 'value', 'href' => null])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['block min-w-0 rounded-xl border border-line bg-white px-4 py-3 sm:px-5 sm:py-4', 'transition hover:border-ink/25' => $href]) }}>
    <span class="block text-sm text-muted">{{ $label }}</span>
    <span class="mt-1 block break-words text-2xl font-semibold leading-tight tracking-tight text-ink tabular-nums sm:text-[1.75rem]">{{ $value }}</span>
    @if ($slot->isNotEmpty())
        <span class="mt-1 block text-xs text-muted">{{ $slot }}</span>
    @endif
</{{ $tag }}>
