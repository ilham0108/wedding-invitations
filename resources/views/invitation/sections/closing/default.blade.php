<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['message']))
        <p class="mx-auto mt-6 max-w-xl leading-relaxed inv-muted">{{ $config['message'] }}</p>
    @endif
    @if (filled($config['signature']))
        <div class="inv-divider"></div>
        <p class="inv-heading text-3xl">{{ $config['signature'] }}</p>
    @endif
    @if (! empty($config['inviters']))
        <p class="mt-8 text-xs uppercase tracking-[0.25em] inv-accent">{{ __('Also inviting') }}</p>
        <ul class="mt-2 space-y-1 text-sm inv-muted">
            @foreach ($config['inviters'] as $inviter)
                <li>{{ $inviter }}</li>
            @endforeach
        </ul>
    @endif
</div>
