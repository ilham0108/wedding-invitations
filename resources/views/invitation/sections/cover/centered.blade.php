@php($hasImage = filled($config['background_image']))

<div class="relative flex min-h-svh items-center justify-center overflow-hidden px-6 py-16"
    @if (! $hasImage) style="background: radial-gradient(ellipse at top, var(--inv-surface), var(--inv-bg) 70%);" @endif>
    @if ($hasImage)
        <img src="{{ $config['background_image'] }}" alt="" fetchpriority="high" decoding="async"
            class="absolute inset-0 h-full w-full object-cover inv-bg-motion--{{ $config['background_motion'] }}"
            style="{{ \App\Support\PhotoStyle::image($config['background_image_focus'] ?? null) }}">
        @if ($config['overlay'])
            <div class="absolute inset-0 bg-black/45"></div>
        @endif
    @endif

    <div @class([
        'relative z-10 w-full max-w-xl',
        'text-center' => ($config['alignment'] ?? 'center') !== 'left',
        'text-left' => ($config['alignment'] ?? 'center') === 'left',
        'text-white' => $hasImage && $config['overlay'],
    ])>
        @if ($invitation->guestName)
            <p class="mb-6 text-sm">{{ __('Dear') }} <span class="font-semibold">{{ $invitation->guestName }}</span>,</p>
        @endif

        @if (filled($config['subtitle']))
            <p class="text-xs uppercase tracking-[0.35em] inv-muted">{{ $config['subtitle'] }}</p>
        @endif

        <h1 class="inv-heading mt-5 text-5xl sm:text-6xl">{{ $config['title'] ?: $invitation->title }}</h1>

        @if (filled($config['date_text']))
            <div class="inv-divider"></div>
            <p class="text-sm tracking-wide">{{ $config['date_text'] }}</p>
        @endif

        @if (filled($config['button_label']))
            <button type="button" class="inv-btn mt-10" x-data
                x-on:click="$el.closest('section').nextElementSibling?.scrollIntoView({ behavior: 'smooth' })">
                <i class="fa-regular fa-envelope-open" aria-hidden="true"></i>{{ $config['button_label'] }}
            </button>
        @endif
    </div>
</div>
