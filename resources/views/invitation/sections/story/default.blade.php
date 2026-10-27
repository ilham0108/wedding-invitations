<div class="inv-container">
    <h2 class="inv-heading text-center text-4xl">{{ $config['heading'] }}</h2>

    <ol class="relative mx-auto mt-12 max-w-xl space-y-10 border-l pl-8" style="border-color: color-mix(in srgb, var(--inv-text) 15%, transparent);">
        @foreach ($config['entries'] as $entry)
            <li class="relative">
                <span class="absolute -left-[2.4rem] top-1 h-3 w-3 rounded-full" style="background: var(--inv-secondary);"></span>
                @if (filled($entry['date'] ?? null))
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] inv-accent">{{ $entry['date'] }}</p>
                @endif
                <h3 class="inv-heading mt-1 text-2xl">{{ $entry['title'] ?? '' }}</h3>
                @if ($entry['image'])
                    <img src="{{ $entry['image'] }}" alt="" loading="lazy" decoding="async" class="mt-4 aspect-video w-full object-cover inv-rounded">
                @endif
                @if (filled($entry['description'] ?? null))
                    <p class="mt-3 text-sm inv-muted">{{ $entry['description'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
</div>
