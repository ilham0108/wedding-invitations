{{-- The picture of a template card: the uploaded thumbnail, or the real cover in a frame scaled to the card's width. --}}
@if ($template->thumbnailUrl())
    <img src="{{ $template->thumbnailUrl() }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">
@else
    <div class="absolute inset-0 overflow-hidden" style="{{ $styleVariables }} background: var(--inv-bg);"
        x-data x-init="new ResizeObserver(([entry]) => $el.style.setProperty('--card-scale', entry.contentRect.width / {{ $frameWidth }})).observe($el)">
        @if ($coverImage)
            <img src="{{ $coverImage }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover">
        @endif
        {{-- Loaded once the card nears the screen: a page of cards should not open every template at once. --}}
        <iframe data-src="{{ $frameUrl }}" x-intersect.once.margin.200px="$el.src = $el.dataset.src" title="{{ $template->name }}" width="{{ $frameWidth }}" height="{{ $frameHeight }}" scrolling="no" tabindex="-1" aria-hidden="true"
            class="absolute left-0 top-0 max-w-none origin-top-left border-0" style="pointer-events: none; transform: scale(var(--card-scale, 0.2));"></iframe>
    </div>
@endif
