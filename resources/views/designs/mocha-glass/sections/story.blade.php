<div>
    <h2 class="mg-title inv-animate" @animate('heading')>{{ $config['heading'] }}</h2>
    <div class="mg-timeline">
        @foreach ($config['entries'] as $entry)
            <div class="mg-story inv-animate" @animate('entries', $loop->index)>
                @if ($entry['icon_class'])
                    <i class="{{ $entry['icon_class'] }} mg-story__heart" aria-hidden="true"></i>
                @else
                    <span class="inv-story-dot mg-story__dot" aria-hidden="true"></span>
                @endif
                <div class="mg-card mg-story__card">
                    @if ($entry['image'])
                        <img src="{{ $entry['image'] }}" alt="" loading="lazy" decoding="async" class="mg-story__img" style="{{ \App\Support\PhotoStyle::image($entry['image_focus'] ?? null, $config['photo_ratio'] ?? null) }}">
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
    @if (filled($config['closing_quote'] ?? null))
        <p class="mg-lead mg-story__quote inv-animate" @animate('closing_quote')>&ldquo;{{ $config['closing_quote'] }}&rdquo;</p>
    @endif
</div>
