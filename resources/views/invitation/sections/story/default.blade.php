<div class="inv-container">
    <h2 class="inv-heading text-center text-4xl">{{ $config['heading'] }}</h2>

    <ol class="relative mx-auto mt-12 max-w-xl space-y-10 border-l pl-8" style="border-color: color-mix(in srgb, var(--inv-text) 15%, transparent);">
        @foreach ($config['entries'] as $entry)
            <li class="relative">
                <span class="absolute -left-[2.85rem] -top-0.5 flex h-7 w-7 items-center justify-center rounded-full text-xs" style="background: var(--inv-secondary); color: var(--inv-bg);">
                    <i class="{{ $entry['icon_class'] }}" aria-hidden="true"></i>
                </span>
                @if (filled($entry['date'] ?? null))
                    <p class="text-xs font-semibold uppercase tracking-[0.25em] inv-accent">{{ $entry['date'] }}</p>
                @endif
                <h3 class="inv-heading mt-1 text-2xl">{{ $entry['title'] ?? '' }}</h3>
                @if ($entry['image'])
                    <img src="{{ $entry['image'] }}" alt="" loading="lazy" decoding="async" class="mt-4 aspect-video w-full object-cover inv-rounded" style="{{ \App\Support\PhotoStyle::image($entry['image_focus'] ?? null, $config['photo_ratio'] ?? null) }}">
                @endif
                @if (filled($entry['description'] ?? null))
                    <p class="mt-3 text-sm inv-muted">{{ $entry['description'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>

    @if (filled($config['closing_quote'] ?? null))
        <p class="mx-auto mt-12 max-w-xl text-center italic inv-muted">&ldquo;{{ $config['closing_quote'] }}&rdquo;</p>
    @endif
</div>
