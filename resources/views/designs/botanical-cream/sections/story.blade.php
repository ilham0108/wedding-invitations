{{-- Timeline with an icon per entry (field "icon") on a thin line, and an optional closing quote. --}}
<div>
    @include('designs.botanical-cream.partials.heading', ['text' => $config['heading']])

    <ol class="bc-timeline">
        @foreach ($config['entries'] as $entry)
            <li class="bc-timeline__item inv-animate">
                <span class="bc-timeline__icon"><i class="{{ $entry['icon_class'] }}" aria-hidden="true"></i></span>
                <div class="bc-timeline__body">
                    <h3>{{ $entry['title'] ?? '' }}</h3>
                    @if (filled($entry['date'] ?? null))
                        <p class="bc-timeline__date">{{ $entry['date'] }}</p>
                    @endif
                    @if (filled($entry['description'] ?? null))
                        <p>{{ $entry['description'] }}</p>
                    @endif
                    @if ($entry['image'])
                        <img src="{{ $entry['image'] }}" alt="" loading="lazy" decoding="async" class="bc-timeline__img"
                            style="{{ \App\Support\PhotoStyle::image($entry['image_focus'] ?? null, $config['photo_ratio'] ?? null) }}">
                    @endif
                </div>
            </li>
        @endforeach
    </ol>

    @if (filled($config['closing_quote'] ?? null))
        <p class="bc-story-quote inv-animate">&ldquo;{{ $config['closing_quote'] }}&rdquo;</p>
    @endif
</div>
