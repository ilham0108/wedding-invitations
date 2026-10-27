{{-- One event ($event) of the event section. --}}
<article class="mg-event">
    <h3>{{ $event['title'] ?? '' }}</h3>
    @if ($event['date_label'])
        <p class="mg-event__when">
            {{ $event['date_label'] }}@if (filled($event['start_time'] ?? null)) | {{ $event['start_time'] }}@if (filled($event['end_time'] ?? null)) &ndash; {{ $event['end_time'] }}@endif @endif
        </p>
    @endif
    @if (filled($event['venue'] ?? null))
        <p class="mg-event__place">{{ $event['venue'] }}</p>
    @endif
    @if (filled($event['address'] ?? null))
        <p class="mg-event__addr">{{ $event['address'] }}</p>
    @endif
    @if (filled($event['description'] ?? null))
        <p class="mg-event__addr">{{ $event['description'] }}</p>
    @endif
    @if (filled($event['notice'] ?? null))
        <p class="mg-notice"><i class="fa-solid fa-user-shield" aria-hidden="true"></i><span>{{ $event['notice'] }}</span></p>
    @endif
    @if ($event['maps_url'] || ($event['calendar_url'] ?? null))
        <div class="mg-actions">
            @if ($event['maps_url'])
                <a class="mg-btn-dark" href="{{ $event['maps_url'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ __('Open Google Maps') }}</a>
            @endif
            @if ($event['calendar_url'] ?? null)
                <a class="mg-btn-dark" href="{{ $event['calendar_url'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>{{ __('Save to calendar') }}</a>
            @endif
        </div>
    @endif
</article>
