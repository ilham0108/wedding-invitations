{{-- After the cover layer slides away, the first screen repeats the names, with a countdown when there is no countdown section. --}}
@php($hasCountdownSection = collect($invitation->sections)->contains('type', 'countdown'))
@php($framed = $variant === 'framed')

<div class="mg-home">
    @if (filled($config['subtitle']))
        <span class="mg-tag">{{ $config['subtitle'] }}</span>
    @endif
    @if ($framed)
        <div class="mg-framebox mg-framebox--{{ $config['frame_style'] }}">
    @endif
    <h2 class="mg-names">@include('designs.mocha-glass.partials.names', ['names' => $config['title'] ?: $invitation->title])</h2>
    @if (filled($config['date_text']))
        <p class="mg-date">{{ $config['date_text'] }}</p>
    @endif
    @if (! $hasCountdownSection && $invitation->countdownTarget)
        @include('designs.mocha-glass.partials.countdown')
    @endif
    @if ($framed)
        </div>
    @endif
    @include('designs.mocha-glass.partials.guest')
</div>
