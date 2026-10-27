@php($details = array_filter(['Men' => $config['men'] ?? '', 'Women' => $config['women'] ?? '', 'Colors' => $config['color_note'] ?? '', 'Please avoid' => $config['avoid'] ?? '']))

<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mx-auto mt-4 max-w-md inv-muted">{{ $config['description'] }}</p>
    @endif
    @if (! empty($config['colors']))
        <div class="mt-8 flex justify-center gap-3">
            @foreach ($config['colors'] as $color)
                <span class="h-10 w-10 rounded-full border border-black/10" style="background: {{ $color }};" title="{{ $color }}"></span>
            @endforeach
        </div>
    @endif
    @if ($details)
        <dl class="mx-auto mt-8 grid max-w-2xl grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ($details as $label => $value)
                <div class="inv-card p-4">
                    <dt class="text-[11px] uppercase tracking-[0.2em] inv-accent">{{ __($label) }}</dt>
                    <dd class="mt-1 text-sm">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
    @if (! empty($config['notes']))
        <ul class="mx-auto mt-6 max-w-md space-y-1 text-left text-sm inv-muted">
            @foreach ($config['notes'] as $note)
                <li><i class="fa-solid fa-check mr-2 inv-accent" aria-hidden="true"></i>{{ $note }}</li>
            @endforeach
        </ul>
    @endif
</div>
