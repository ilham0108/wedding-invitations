<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mx-auto mt-4 max-w-md inv-muted">{{ $config['description'] }}</p>
    @endif
    @if (! empty($config['items']))
        <ul class="mx-auto mt-10 grid max-w-2xl grid-cols-2 gap-6 sm:grid-cols-4">
            @foreach ($config['items'] as $item)
                <li>
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full inv-card text-xl inv-accent"><i class="{{ $item['icon_class'] }}" aria-hidden="true"></i></span>
                    <p class="mt-3 text-sm">{{ $item['text'] ?? '' }}</p>
                </li>
            @endforeach
        </ul>
    @endif
</div>
