{{-- One event ($event): an icon picked from its title (ring for the ceremony, glasses for the reception), then date, time and place rows. --}}
@php
    $title = mb_strtolower((string) ($event['title'] ?? ''));
    $icon = match (true) {
        \Illuminate\Support\Str::contains($title, ['akad', 'nikah', 'pemberkatan', 'matrimon', 'ceremony', 'holy']) => 'fa-solid fa-ring',
        \Illuminate\Support\Str::contains($title, ['resepsi', 'reception', 'pesta', 'party', 'ngunduh']) => 'fa-solid fa-champagne-glasses',
        default => 'fa-regular fa-calendar-check',
    };
@endphp

<article class="bc-event">
    <i class="{{ $icon }} bc-event__icon" aria-hidden="true"></i>
    <h3>{{ $event['title'] ?? '' }}</h3>
    <ul class="bc-event__rows">
        @if ($event['date_label'])
            <li><i class="fa-regular fa-calendar" aria-hidden="true"></i>{{ $event['date_label'] }}</li>
        @endif
        @if (filled($event['start_time'] ?? null))
            <li><i class="fa-regular fa-clock" aria-hidden="true"></i>{{ $event['start_time'] }}@if (filled($event['end_time'] ?? null)) &ndash; {{ $event['end_time'] }}@endif</li>
        @endif
        @if (filled($event['venue'] ?? null) || filled($event['address'] ?? null))
            <li>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span>
                    @if (filled($event['venue'] ?? null))<b>{{ $event['venue'] }}</b>@endif
                    @if (filled($event['address'] ?? null))<span class="bc-event__address">{{ $event['address'] }}</span>@endif
                </span>
            </li>
        @endif
    </ul>
    @if (filled($event['description'] ?? null))
        <p class="bc-event__note">{{ $event['description'] }}</p>
    @endif
    @if (filled($event['notice'] ?? null))
        <p class="bc-notice"><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span>{{ $event['notice'] }}</span></p>
    @endif
    @if ($event['maps_url'] || ($event['calendar_url'] ?? null))
        <div class="bc-actions">
            @if ($event['maps_url'])
                <a class="bc-btn" href="{{ $event['maps_url'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ __('Open Google Maps') }}</a>
            @endif
            @if ($event['calendar_url'] ?? null)
                <a class="bc-btn bc-btn--outline" href="{{ $event['calendar_url'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>{{ __('Save to calendar') }}</a>
            @endif
        </div>
    @endif
</article>
