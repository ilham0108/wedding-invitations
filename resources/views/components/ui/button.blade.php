@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'icon' => null,
])

@php
    $variants = [
        'primary' => 'bg-ink text-white hover:bg-ink-soft focus-visible:ring-ink',
        'accent' => 'bg-accent text-white hover:bg-accent-hover focus-visible:ring-accent',
        'secondary' => 'bg-white text-ink border border-line hover:border-ink/30 focus-visible:ring-ink',
        'ghost' => 'text-ink-soft hover:bg-sage-soft hover:text-ink focus-visible:ring-ink',
        'danger' => 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-600',
    ];
    $sizes = [
        'sm' => 'h-8 px-3 text-xs',
        'md' => 'h-10 px-4 text-sm',
        'lg' => 'h-12 px-6 text-base',
    ];
    $classes = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<i class="{{ $icon }}" aria-hidden="true"></i>@endif
        {{ $slot }}
    </button>
@endif
