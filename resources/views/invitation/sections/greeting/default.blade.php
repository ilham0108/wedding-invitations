{{-- Greeting: an optional ornament, the opening line, the invitation message and the names ("A & B" on three lines). --}}
@php($names = preg_split('/\s*&\s*/u', (string) ($config['names'] ?: $invitation->names()), 2))

<div class="inv-container text-center">
    @if ($config['emblem'])
        <img src="{{ $config['emblem'] }}" alt="" aria-hidden="true" height="80" loading="lazy" decoding="async" class="mx-auto mb-6 h-20 w-auto max-w-[60%] object-contain" @animate('emblem')>
    @endif
    @if (filled($config['opening']))
        <p dir="auto" class="inv-heading text-2xl italic" @animate('opening')>{{ $config['opening'] }}</p>
    @endif
    @if (filled($config['message']))
        <p class="mx-auto mt-5 max-w-md leading-relaxed inv-muted" @animate('message')>{{ $config['message'] }}</p>
    @endif
    <h2 class="inv-heading mt-8 text-5xl leading-tight" @animate('names')>
        {{ $names[0] }}
        @isset($names[1])
            <span class="my-2 block text-3xl inv-accent">&amp;</span>{{ $names[1] }}
        @endisset
    </h2>
</div>
