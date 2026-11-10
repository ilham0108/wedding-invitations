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

    <div @class(['bc-cover__text inv-animate', 'bc-frame bc-frame--'.$config['frame_style'] => $framed, 'bc-frame--arch' => $framed && $config['frame_shape'] === 'arch'])>
        @if (filled($config['subtitle']))
            <p class="bc-eyebrow" @animate('subtitle')>{{ $config['subtitle'] }}</p>
        @endif
        <h1 class="bc-names" @animate('title')>@include('designs.botanical-cream.partials.names', ['names' => $config['title'] ?: $invitation->title])</h1>
        @if (filled($config['date_text']))
            <p class="bc-pill" @animate('date_text')>{{ $config['date_text'] }}</p>
        @endif
        @if ($invitation->guestName)
            <p class="bc-guest">{{ __('To:') }} <b>{{ $invitation->guestName }}</b></p>
        @endif
    </div>

    @php($iconOnly = $config['button_display'] !== 'label')
    <a href="#" @class(['bc-scroll inv-animate', 'inv-cue' => $iconOnly]) @animate('button_label') data-bc-scroll @if ($iconOnly) aria-label="{{ $config['button_label'] ?: __('Scroll down') }}" @endif>
        @if ($iconOnly)
            @include('invitation.partials.cover-cue', ['cue' => $config['button_display']])
        @else
            <span class="bc-scroll__mouse" aria-hidden="true"><span></span></span>
            <span class="bc-scroll__label">{{ $config['button_label'] ?: __('Scroll down') }}</span>
        @endif
    </a>
</div>
