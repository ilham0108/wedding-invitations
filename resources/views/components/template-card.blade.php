@php($frame = ['frameWidth' => \App\View\Components\TemplateCard::FRAME_WIDTH, 'frameHeight' => \App\View\Components\TemplateCard::FRAME_HEIGHT])

@if ($compact)
    {{-- Tall cover and the name only; the name's link covers the whole card and opens the template preview. --}}
    <div {{ $attributes->class('group relative block text-center') }}>
        <div class="relative aspect-[9/14] overflow-hidden rounded-xl shadow-[0_18px_40px_-22px_rgb(31_45_42/0.45)] ring-1 ring-black/5 transition duration-300 group-hover:-translate-y-1.5 group-hover:shadow-[0_26px_50px_-24px_rgb(31_45_42/0.55)]">
            @include('components.template-card-cover', $frame)
            @if ($template->is_premium)
                <span class="absolute right-2 top-2 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium text-ink">{{ __('Premium') }}</span>
            @endif
        </div>
        <a href="{{ route('templates.preview', $template) }}" target="_blank" rel="noopener" class="mt-3 block text-sm text-ink-soft after:absolute after:inset-0 group-hover:text-ink">{{ $template->name }}</a>
    </div>
@else
<article {{ $attributes->class('group overflow-hidden rounded-xl border border-line bg-white shadow-soft transition hover:border-ink/20') }}>
    <div class="relative aspect-[9/14]">
        @include('components.template-card-cover', $frame)
        <a href="{{ route('templates.preview', $template) }}" target="_blank" rel="noopener" aria-label="{{ __('Preview') }}: {{ $template->name }}" class="absolute inset-0 flex items-start justify-center pt-4">
            <span class="rounded-full bg-ink px-3 py-1 text-[11px] text-white opacity-0 transition group-hover:opacity-100">{{ __('Preview') }}</span>
        </a>
    </div>

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
