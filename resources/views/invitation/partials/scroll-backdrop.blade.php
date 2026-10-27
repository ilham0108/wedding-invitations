{{--
    Background photos that change while the guest scrolls (section field "background_photo").
    One layer per distinct photo; invitation.js shows the photo of the last section that reached
    the middle of the screen (sections without a photo keep the previous one). Photos load when
    first shown, the rest after the page finished loading. $class positions the container.
--}}
@php
    // One layer per photo, positioned at the focal point of the first section that uses it.
    $photos = collect($invitation->sections)
        ->filter(fn (array $section): bool => filled($section['config']['background_photo'] ?? null))
        ->unique(fn (array $section): string => $section['config']['background_photo'])
        ->mapWithKeys(fn (array $section): array => [$section['config']['background_photo'] => \App\Support\PhotoStyle::background($section['config']['background_photo_focus'] ?? null)]);
@endphp

@if ($photos->isNotEmpty())
    <div class="inv-backdrop {{ $class ?? '' }}" data-inv-backdrop-root aria-hidden="true">
        @foreach ($photos as $photo => $position)
            <div class="inv-backdrop__layer" data-photo="{{ $photo }}" data-src="{{ \App\Support\CssUrl::wrap($photo) }}" @if ($position !== '') style="{{ $position }}" @endif></div>
        @endforeach
    </div>
@endif
