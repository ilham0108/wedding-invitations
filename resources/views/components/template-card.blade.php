@if ($compact)
    {{-- Tall cover and the name only; opens the template preview. --}}
    <a href="{{ route('templates.preview', $template) }}" target="_blank" rel="noopener" {{ $attributes->class('group block text-center') }}>
        <span class="relative block aspect-[9/14] overflow-hidden rounded-xl shadow-[0_18px_40px_-22px_rgb(31_45_42/0.45)] ring-1 ring-black/5 transition duration-300 group-hover:-translate-y-1.5 group-hover:shadow-[0_26px_50px_-24px_rgb(31_45_42/0.55)]">
            @if ($template->thumbnailUrl())
                <img src="{{ $template->thumbnailUrl() }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover">
            @elseif ($coverImage)
                <link rel="stylesheet" href="{{ $fontStylesheetUrl }}">
                <span class="absolute inset-0 flex flex-col items-center justify-center px-3 text-center text-white" style="{{ $styleVariables }}">
                    <img src="{{ $coverImage }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    @if ($coverOverlay)<span class="absolute inset-0 bg-gradient-to-b from-black/10 via-black/25 to-black/60"></span>@endif
                    <span class="relative" style="text-shadow: 0 2px 10px rgb(0 0 0 / .45);">
                        @if (filled($coverSubtitle))<span class="block text-[8px] uppercase tracking-[0.3em] opacity-90">{{ $coverSubtitle }}</span>@endif
                        <span class="mt-2 block text-2xl leading-tight" style="font-family: var(--inv-font-heading);">{{ $sampleTitle }}</span>
                        @if (filled($coverDate))<span class="mt-2 block text-[9px] tracking-wider opacity-90">{{ $coverDate }}</span>@endif
                    </span>
                </span>
            @else
                <link rel="stylesheet" href="{{ $fontStylesheetUrl }}">
                <span class="absolute inset-0 flex flex-col items-center justify-center px-3 text-center"
                    style="{{ $styleVariables }} background: var(--inv-bg); color: var(--inv-text);">
                    <span class="text-[8px] uppercase tracking-[0.3em]" style="opacity: .7;">{{ $template->category->name }}</span>
                    <span class="mt-3 text-2xl leading-tight" style="font-family: var(--inv-font-heading);">{{ $sampleTitle }}</span>
                    <span class="my-3 h-px w-10" style="background: var(--inv-secondary);"></span>
                </span>
            @endif
            @if ($template->is_premium)
                <span class="absolute right-2 top-2 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium text-ink">{{ __('Premium') }}</span>
            @endif
        </span>
        <span class="mt-3 block text-sm text-ink-soft group-hover:text-ink">{{ $template->name }}</span>
    </a>
@else
<article {{ $attributes->class('group overflow-hidden rounded-xl border border-line bg-white shadow-soft transition hover:border-ink/20') }}>
    <a href="{{ route('templates.preview', $template) }}" class="block" target="_blank" rel="noopener">
        @if ($template->thumbnailUrl())
            <img src="{{ $template->thumbnailUrl() }}" alt="{{ $template->name }}" loading="lazy" decoding="async" class="aspect-[4/5] w-full object-cover">
        @elseif ($coverImage)
            {{-- The template's own cover: background photo with title, subtitle and date. --}}
            <link rel="stylesheet" href="{{ $fontStylesheetUrl }}">
            <div class="relative flex aspect-[4/5] flex-col items-center justify-end overflow-hidden px-5 pb-10 text-center text-white" style="{{ $styleVariables }}">
                <img src="{{ $coverImage }}" alt="" loading="lazy" decoding="async" class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105">
                @if ($coverOverlay)
                    <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-black/20 to-black/75"></div>
                @endif
                <div class="relative" style="text-shadow: 0 2px 12px rgb(0 0 0 / .45);">
                    @if (filled($coverSubtitle))
                        <span class="block text-[10px] uppercase tracking-[0.3em] opacity-90">{{ $coverSubtitle }}</span>
                    @endif
                    <span class="mt-2 block text-3xl leading-tight" style="font-family: var(--inv-font-heading);">{{ $sampleTitle }}</span>
                    @if (filled($coverDate))
                        <span class="mt-2 block text-xs opacity-90">{{ $coverDate }}</span>
                    @endif
                </div>
                <span class="absolute top-4 rounded-full px-3 py-1 text-[11px] opacity-0 transition group-hover:opacity-100"
                    style="background: var(--inv-primary); color: var(--inv-on-primary);">{{ __('Preview') }}</span>
            </div>
        @else
            <div class="relative flex aspect-[4/5] flex-col items-center justify-center px-6 text-center"
                style="{{ $styleVariables }} background: var(--inv-bg); color: var(--inv-text);">
                <span class="text-[10px] uppercase tracking-[0.3em]" style="opacity: .7;">{{ $template->category->name }}</span>
                <span class="mt-3 text-3xl leading-tight" style="font-family: var(--inv-font-heading);">{{ $sampleTitle }}</span>
                <span class="my-4 h-px w-12" style="background: var(--inv-secondary);"></span>
                <span class="flex gap-1.5">
                    @foreach (['primary_color', 'secondary_color', 'surface_color'] as $colorKey)
                        <span class="h-3 w-3 rounded-full border border-black/10" style="background: {{ $theme[$colorKey] }};"></span>
                    @endforeach
                </span>
                <span class="absolute bottom-4 rounded-full px-3 py-1 text-[11px] opacity-0 transition group-hover:opacity-100"
                    style="background: var(--inv-primary); color: var(--inv-on-primary);">{{ __('Preview') }}</span>
            </div>
        @endif
    </a>

    <div class="flex items-center justify-between gap-3 p-4">
        <div class="min-w-0">
            <h3 class="truncate font-medium text-ink">{{ $template->name }}</h3>
            <p class="text-xs text-muted">{{ $template->category->name }}</p>
        </div>
        @if ($template->is_premium)
            <x-ui.badge variant="accent">{{ __('Premium') }}</x-ui.badge>
        @endif
    </div>

    @if ($useUrl)
        <div class="flex gap-2 px-4 pb-4">
            <x-ui.button :href="route('templates.preview', $template)" variant="secondary" size="sm" class="flex-1" target="_blank">{{ __('Preview') }}</x-ui.button>
            <x-ui.button :href="$useUrl" size="sm" class="flex-1">{{ __('Use Template') }}</x-ui.button>
        </div>
    @endif
</article>
@endif
