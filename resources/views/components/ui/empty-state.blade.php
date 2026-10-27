@props(['icon' => 'fa-regular fa-folder-open', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-12 text-center') }}>
    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-accent-soft text-xl text-accent">
        <i class="{{ $icon }}" aria-hidden="true"></i>
    </span>
    <h3 class="mt-4 font-display text-xl text-ink">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-muted">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-6">{{ $action }}</div>
    @endisset
</div>
