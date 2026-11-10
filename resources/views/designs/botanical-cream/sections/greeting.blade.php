{{-- Greeting: ornament, opening line in italic, the invitation message and the names in the heading font. --}}
<div class="bc-greeting inv-animate">
    @if ($config['emblem'])
        <img src="{{ $config['emblem'] }}" alt="" aria-hidden="true" height="72" loading="lazy" decoding="async" class="bc-greeting__emblem" @animate('emblem')>
    @endif
    @if (filled($config['opening']))
        <p class="bc-greeting__opening" dir="auto" @animate('opening')>{{ $config['opening'] }}</p>
    @endif
    @if (filled($config['message']))
        <p class="bc-lead" @animate('message')>{{ $config['message'] }}</p>
    @endif
    <h2 class="bc-greeting__names" @animate('names')>@include('designs.botanical-cream.partials.names', ['names' => $config['names'] ?: $invitation->names()])</h2>
</div>
