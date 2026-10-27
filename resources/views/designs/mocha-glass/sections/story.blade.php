<div>
    <h2 class="mg-title inv-animate">{{ $config['heading'] }}</h2>
    <div class="mg-timeline">
        @foreach ($config['entries'] as $entry)
            <div class="mg-story inv-animate">
                <i class="fa-solid fa-heart mg-story__heart" aria-hidden="true"></i>
                <div class="mg-card mg-story__card">
                    @if ($entry['image'])
                        <img src="{{ $entry['image'] }}" alt="" loading="lazy" decoding="async" class="mg-story__img">
                    @endif
                    @if (filled($entry['date'] ?? null))
                        <p class="mg-story__date">{{ $entry['date'] }}</p>
                    @endif
                    <h3>{{ $entry['title'] ?? '' }}</h3>
                    @if (filled($entry['description'] ?? null))
                        <p>{{ $entry['description'] }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
