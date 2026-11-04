{{-- Greeting: ornament, opening line in italic, the invitation message and the names on three lines. --}}
<div class="mg-greeting inv-animate">
    @if ($config['emblem'])
        <img src="{{ $config['emblem'] }}" alt="" aria-hidden="true" height="72" loading="lazy" decoding="async" class="mg-greeting__emblem">
    @endif
    @if (filled($config['opening']))
        <p class="mg-greeting__opening" dir="auto">{{ $config['opening'] }}</p>
    @endif
    @if (filled($config['message']))
        <p class="mg-lead">{{ $config['message'] }}</p>
    @endif
    <h2 class="mg-names mg-greeting__names">@include('designs.mocha-glass.partials.names', ['names' => $config['names'] ?: $invitation->names()])</h2>
</div>
