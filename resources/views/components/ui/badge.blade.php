@props(['variant' => 'neutral'])

@php
    $variants = [
        'neutral' => 'bg-paper text-ink-soft ring-line',
        'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'danger' => 'bg-red-50 text-red-700 ring-red-200',
        'accent' => 'bg-accent-soft text-accent-hover ring-accent/20',
    ];
@endphp

<span {{ $attributes->class('inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($variants[$variant] ?? $variants['neutral'])) }}>
    {{ $slot }}
</span>
