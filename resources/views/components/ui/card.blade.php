@props(['title' => null, 'description' => null, 'padding' => 'p-6'])

<section {{ $attributes->class('rounded-xl border border-line bg-white shadow-soft') }}>
    @if ($title || isset($actions))
        <header class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-ink">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-sm text-muted">{{ $description }}</p>
                @endif
            </div>
            {{ $actions ?? '' }}
        </header>
    @endif
    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</section>
