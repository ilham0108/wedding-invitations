<div class="inv-container text-center">
    @if ($config['photo'])
        <img src="{{ $config['photo'] }}" alt="" loading="lazy" decoding="async" class="mx-auto mb-8 aspect-[4/3] w-full max-w-xl object-cover inv-rounded" @animate('photo')
            style="{{ \App\Support\PhotoStyle::image($config['photo_focus'] ?? null) }}">
    @endif
    <h2 class="inv-heading text-4xl" @animate('heading')>{{ $config['heading'] }}</h2>
    @if (filled($config['message']))
        <p class="mx-auto mt-6 max-w-xl leading-relaxed inv-muted" @animate('message')>{{ $config['message'] }}</p>
    @endif
    @if (filled($config['signature']))
        <div class="inv-divider"></div>
        @if (filled($config['sign_off'] ?? null))
            <p class="mb-2 inv-muted" @animate('sign_off')>{{ $config['sign_off'] }}</p>
        @endif
        <p class="inv-heading text-3xl" @animate('signature')>{{ $config['signature'] }}</p>
    @endif
    @if (! empty($config['inviters']))
        <p class="mt-8 text-xs uppercase tracking-[0.25em] inv-accent" @animate('inviters')>{{ __('Also inviting') }}</p>
        <ul class="mt-2 space-y-1 text-sm inv-muted" @animate('inviters')>
            @foreach ($config['inviters'] as $inviter)
                <li>{{ $inviter }}</li>
            @endforeach
        </ul>
    @endif
</div>
