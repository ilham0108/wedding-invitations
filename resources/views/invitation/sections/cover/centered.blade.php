@php($hasImage = filled($config['background_image']))

<div class="inv-cover relative flex min-h-svh items-center justify-center overflow-hidden px-6 py-16" {!! \App\Support\CoverTitle::attributes($config['title_position'] ?? null) !!}
    style="{{ $hasImage ? '' : 'background: radial-gradient(ellipse at top, var(--inv-surface), var(--inv-bg) 70%);' }} {{ \App\Support\CoverTitle::style($config['title_offset'] ?? 0) }}">
    @if ($hasImage)
        <img src="{{ $config['background_image'] }}" alt="" fetchpriority="high" decoding="async"
            class="absolute inset-0 h-full w-full object-cover inv-bg-motion--{{ $config['background_motion'] }}"
            style="{{ \App\Support\PhotoStyle::image($config['background_image_focus'] ?? null) }}">
        @if ($config['overlay'])
            <div class="absolute inset-0 bg-black/45"></div>
        @endif
    @endif

    <div @class([
        'inv-cover__content relative z-10 w-full max-w-xl',
        'text-center' => ($config['alignment'] ?? 'center') !== 'left',
        'text-left' => ($config['alignment'] ?? 'center') === 'left',
        'text-white' => $hasImage && $config['overlay'],
    ])>
        @if ($invitation->guestName)
            <p class="mb-6 text-sm">{{ __('Dear') }} <span class="font-semibold">{{ $invitation->guestName }}</span>,</p>
        @endif

        @if (filled($config['arabic_text'] ?? null))
            <p class="inv-cover-arabic" lang="ar" dir="rtl" @animate('arabic_text')>{{ $config['arabic_text'] }}</p>
        @endif

        @if (filled($config['subtitle']))
            <p dir="auto" class="text-xs uppercase tracking-[0.35em] inv-muted" @animate('subtitle')>{{ $config['subtitle'] }}</p>
        @endif

        <h1 dir="auto" class="inv-heading mt-5 text-5xl sm:text-6xl" @animate('title')>{{ $config['title'] ?: $invitation->title }}</h1>

        @if (filled($config['date_text']))
            <div class="inv-divider"></div>
            <p dir="auto" class="text-sm tracking-wide" @animate('date_text')>{{ $config['date_text'] }}</p>
        @endif

        @unless (\App\Support\CoverButton::atBottom($config['button_position'] ?? null))
            @include('invitation.sections.cover._button', ['onPhoto' => $hasImage])
        @endunless
    </div>

    @if (\App\Support\CoverButton::atBottom($config['button_position'] ?? null))
        <div class="inv-cover-bar">
            @include('invitation.sections.cover._button', ['onPhoto' => $hasImage])
        </div>
    @endif
</div>
