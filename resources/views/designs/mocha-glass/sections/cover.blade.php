{{-- After the cover layer slides away, the first screen repeats the names, with a countdown when there is no countdown section. --}}
@php($hasCountdownSection = collect($invitation->sections)->contains('type', 'countdown'))
@php($framed = $variant === 'framed')

<div class="mg-home">
    @if (filled($config['subtitle']))
        <span class="mg-tag" dir="auto" @animate('subtitle')>{{ $config['subtitle'] }}</span>
    @endif
    @if ($framed)
        <div @class(['mg-framebox mg-framebox--'.$config['frame_style'], 'mg-framebox--arch' => $config['frame_shape'] === 'arch'])>
    @endif
    @if (filled($config['arabic_text'] ?? null))
        <p class="inv-cover-arabic" lang="ar" dir="rtl" @animate('arabic_text')>{{ $config['arabic_text'] }}</p>
    @endif
    <h2 class="mg-names" dir="auto" @animate('title')>@include('designs.mocha-glass.partials.names', ['names' => $config['title'] ?: $invitation->title])</h2>
    @if (filled($config['date_text']))
        <p class="mg-date" dir="auto" @animate('date_text')>{{ $config['date_text'] }}</p>
    @endif
    @if (! $hasCountdownSection && $invitation->countdownTarget)
        @include('designs.mocha-glass.partials.countdown')
    @endif
    @if ($framed)
        </div>
    @endif
    @include('designs.mocha-glass.partials.guest')
</div>
