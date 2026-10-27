<div class="inv-container">
    <h2 class="inv-heading text-center text-4xl">{{ $config['heading'] }}</h2>

    <div class="mx-auto mt-10 max-w-xl divide-y" style="border-color: color-mix(in srgb, var(--inv-text) 12%, transparent);">
        @foreach ($config['items'] as $item)
            <div class="flex gap-6 py-4">
                <p class="w-20 shrink-0 font-semibold tabular-nums inv-accent">{{ $item['time'] ?? '' }}</p>
                <div>
                    <p class="font-medium">{{ $item['title'] ?? '' }}</p>
                    @if (filled($item['description'] ?? null))
                        <p class="mt-1 text-sm inv-muted">{{ $item['description'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
