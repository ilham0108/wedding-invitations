<article class="inv-card p-6 sm:p-8">
    <h3 class="inv-heading text-2xl">{{ $event['title'] ?? '' }}</h3>
    <div class="inv-divider !my-4"></div>
    @if ($event['date_label'])
        <p class="font-medium">{{ $event['date_label'] }}</p>
    @endif
    @if (filled($event['start_time'] ?? null))
        <p class="mt-1 text-sm inv-muted">
            {{ $event['start_time'] }}@if (filled($event['end_time'] ?? null)) – {{ $event['end_time'] }}@endif
            ({{ $invitation->timezone }})
        </p>
    @endif
    @if (filled($event['venue'] ?? null))
        <p class="mt-4 font-medium">{{ $event['venue'] }}</p>
    @endif
    @if (filled($event['address'] ?? null))
        <p class="mt-1 text-sm inv-muted">{{ $event['address'] }}</p>
    @endif
    @if (filled($event['description'] ?? null))
        <p class="mt-4 text-sm inv-muted">{{ $event['description'] }}</p>
    @endif
    @if (filled($event['notice'] ?? null))
        <p class="mt-4 rounded-lg border border-dashed border-black/20 px-4 py-3 text-sm"><i class="fa-solid fa-user-shield mr-1" aria-hidden="true"></i>{{ $event['notice'] }}</p>
    @endif
    @if ($event['maps_url'] || ($event['calendar_url'] ?? null))
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            @if ($event['maps_url'])
                <a href="{{ $event['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="inv-btn">
                    <i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ __('Open Google Maps') }}
                </a>
            @endif
            @if ($event['calendar_url'] ?? null)
                <a href="{{ $event['calendar_url'] }}" target="_blank" rel="noopener noreferrer" class="inv-btn">
                    <i class="fa-regular fa-calendar-plus" aria-hidden="true"></i>{{ __('Save to calendar') }}
                </a>
            @endif
        </div>
    @endif
</article>
