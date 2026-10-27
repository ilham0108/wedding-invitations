<div>
    <h2 class="mg-title inv-animate">{{ $config['heading'] }}</h2>
    <div class="mg-timeline">
        @foreach ($config['entries'] as $entry)
            <div class="mg-story inv-animate">
                <i class="{{ $entry['icon_class'] }} mg-story__heart" aria-hidden="true"></i>
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
        <p class="mg-lead mg-story__quote inv-animate">&ldquo;{{ $config['closing_quote'] }}&rdquo;</p>
    @endif
</div>
