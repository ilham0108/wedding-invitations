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
        // A solid frame uses the theme surface, so its text keeps the theme text color.
        'text-white' => $hasImage && $config['overlay'] && $config['frame_style'] !== 'solid',
        'inv-frame inv-frame--'.$config['frame_style'].' px-6 py-14 sm:px-10',
        'inv-frame--arch' => $config['frame_shape'] === 'arch',
    ])>
        @if ($invitation->guestName)
            <p class="mb-6 text-sm">{{ __('Dear') }} <span class="font-semibold">{{ $invitation->guestName }}</span>,</p>
        @endif

        @if (filled($config['subtitle']))
            <p class="text-xs uppercase tracking-[0.35em] inv-muted" @animate('subtitle')>{{ $config['subtitle'] }}</p>
        @endif

        <h1 class="inv-heading mt-5 text-5xl sm:text-6xl" @animate('title')>{{ $config['title'] ?: $invitation->title }}</h1>

        @if (filled($config['date_text']))
            <div class="inv-divider"></div>
            <p class="text-sm tracking-wide" @animate('date_text')>{{ $config['date_text'] }}</p>
        @endif

        @include('invitation.sections.cover._button', ['onPhoto' => $hasImage && $config['frame_style'] !== 'solid'])
    </div>
</div>
