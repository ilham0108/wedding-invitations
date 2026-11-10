{{-- Greeting: ornament, opening line in italic, the invitation message and the names on three lines. --}}
<div class="mg-greeting inv-animate">
    @if ($config['emblem'])
        <img src="{{ $config['emblem'] }}" alt="" aria-hidden="true" height="72" loading="lazy" decoding="async" class="mg-greeting__emblem" @animate('emblem')>
    @endif
    @if (filled($config['opening']))
        <p class="mg-greeting__opening" dir="auto" @animate('opening')>{{ $config['opening'] }}</p>
    @endif
    @if (filled($config['message']))
        <p class="mg-lead" @animate('message')>{{ $config['message'] }}</p>
    @endif
    <h2 class="mg-names mg-greeting__names" @animate('names')>@include('designs.mocha-glass.partials.names', ['names' => $config['names'] ?: $invitation->names()])</h2>
</div>
