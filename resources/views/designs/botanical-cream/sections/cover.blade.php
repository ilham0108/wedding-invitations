{{--
    Cover: the photo fades into the cream page, names in script below it. "framed" puts the text in a
    frame (frame_style). The page top bar carries the monogram; the arrow scrolls to the next section.
--}}
@php
    $framed = $variant === 'framed';
    $hasImage = filled($config['background_image']);
@endphp

<div @class([
    'bc-cover',
    'bc-cover--left' => ($config['alignment'] ?? 'center') === 'left',
    'bc-cover--no-photo' => ! $hasImage,
    'bc-cover--dim' => $config['overlay'],
]) {!! \App\Support\CoverTitle::attributes($config['title_position'] ?? null) !!} style="{{ \App\Support\CoverTitle::style($config['title_offset'] ?? 0) }}">
    @if ($hasImage)
        <div class="bc-cover__photo">
            <img src="{{ $config['background_image'] }}" alt="" fetchpriority="high" decoding="async"
                class="inv-bg-motion--{{ $config['background_motion'] }}" style="{{ \App\Support\PhotoStyle::image($config['background_image_focus'] ?? null) }}">
        </div>
    @endif

    @include('invitation.partials.cover-photo', ['where' => 'free', 'alt' => $config['title'] ?: $invitation->title])

    <div @class(['bc-cover__text inv-animate', 'bc-frame bc-frame--'.$config['frame_style'] => $framed, 'bc-frame--arch' => $framed && $config['frame_shape'] === 'arch'])>
        @include('invitation.partials.cover-photo', ['alt' => $config['title'] ?: $invitation->title])
        @if (filled($config['arabic_text'] ?? null))
            <p class="inv-cover-arabic" lang="ar" dir="rtl" @animate('arabic_text')>{{ $config['arabic_text'] }}</p>
        @endif
        @if (filled($config['subtitle']))
            <p class="bc-eyebrow" dir="auto" @animate('subtitle')>{{ $config['subtitle'] }}</p>
        @endif
        <h1 class="bc-names" dir="auto" @animate('title')>@include('designs.botanical-cream.partials.names', ['names' => $config['title'] ?: $invitation->title, 'layout' => $config['title_layout'] ?? null])</h1>
        @if (filled($config['date_text']))
            <p class="bc-pill" dir="auto" @animate('date_text')>{{ $config['date_text'] }}</p>
        @endif
        @unless (\App\Support\CoverButton::atBottom($config['button_position'] ?? null, designBottom: true))
            @include('invitation.partials.cover-guest', ['class' => 'bc-guest'])
            @include('designs.botanical-cream.partials.cover-scroll')
        @endunless
    </div>

    {{-- The design's own place is the bottom of the screen, below the text block; the guest's name goes with it. --}}
    @if (\App\Support\CoverButton::atBottom($config['button_position'] ?? null, designBottom: true))
        @include('invitation.partials.cover-guest', ['class' => 'bc-guest bc-guest--bottom'])
        @include('designs.botanical-cream.partials.cover-scroll')
    @endif
</div>
