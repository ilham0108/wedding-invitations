{{-- One event ($event): its icon (chosen, or picked from the title by InvitationRenderer), then date, time and place rows. --}}

<article class="bc-event">
    <i class="{{ $event['icon_class'] }} bc-event__icon" aria-hidden="true"></i>
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
