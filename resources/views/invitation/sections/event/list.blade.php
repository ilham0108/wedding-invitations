{{--
    Events as rows: icon, title, date and time, separated by a line. When every event shares one place
    ("shared_place"), the place and its map button come once at the end instead of under each event.
--}}
@php($shared = $config['shared_place'] ?? null)

<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl" @animate('heading')>{{ $config['heading'] }}</h2>

    @if (empty($config['events']))
        <p class="mt-6 inv-muted" @animate('events')>{{ __('Event details will be shared soon.') }}</p>
    @else
        <div class="mx-auto mt-8 max-w-md text-left">
            @foreach ($config['events'] as $event)
                <article @class(['inv-event-row', 'inv-event-row--ruled' => ! $loop->first]) @animate('events', $loop->index)>
                    <i class="{{ $event['icon_class'] }} inv-event-row__icon inv-accent" aria-hidden="true"></i>
                    <div class="min-w-0">
                        <h3 class="inv-heading text-3xl">{{ $event['title'] ?? '' }}</h3>
                        @if ($event['date_label'])
                            <p class="mt-2">{{ $event['date_label'] }}</p>
                        @endif
                        @if ($event['time_label'])
                            <p>{{ __('At :time', ['time' => $event['time_label']]) }}</p>
                        @endif
                        @if (! $shared && (filled($event['venue'] ?? null) || filled($event['address'] ?? null)))
                            <p class="mt-3 font-medium">{{ $event['venue'] ?? '' }}</p>
                            @if (filled($event['address'] ?? null))
                                <p class="text-sm inv-muted">{{ $event['address'] }}</p>
                            @endif
                        @endif
                        @if (filled($event['description'] ?? null))
                            <p class="mt-3 text-sm inv-muted">{{ $event['description'] }}</p>
                        @endif
                        @if (filled($event['notice'] ?? null))
                            <p class="mt-3 rounded-lg border border-dashed border-black/20 px-4 py-3 text-sm"><i class="fa-solid fa-user-shield mr-1" aria-hidden="true"></i>{{ $event['notice'] }}</p>
                        @endif
                        @if ((! $shared && $event['maps_url']) || ($event['calendar_url'] ?? null))
                            <div class="mt-4 flex flex-wrap gap-2">
                                @if (! $shared && $event['maps_url'])
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
                    </div>
                </article>
            @endforeach

            @if ($shared)
                <div class="inv-event-row inv-event-row--ruled" @animate('events', count($config['events']))>
                    <i class="fa-solid fa-location-dot inv-event-row__icon inv-accent" aria-hidden="true"></i>
                    <div class="min-w-0">
                        @if ($shared['venue'] !== '')
                            <p class="text-lg font-semibold">{{ $shared['venue'] }}</p>
                        @endif
                        @if ($shared['address'] !== '')
                            <p class="mt-1">{{ $shared['address'] }}</p>
                        @endif
                    </div>
                </div>
                @if ($shared['maps_url'])
                    <div class="mt-2 text-center" @animate('events', count($config['events']))>
                        <a href="{{ $shared['maps_url'] }}" target="_blank" rel="noopener noreferrer" class="inv-btn">
                            <i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ __('Open Google Maps') }}
                        </a>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
