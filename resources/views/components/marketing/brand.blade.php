{{-- Marketing wordmark: a line-art leaf sprig, the app name in serif and a small tagline. --}}
@props(['tagline' => true])

<span {{ $attributes->class('inline-flex items-center gap-2.5 text-olive') }}>
    <svg viewBox="0 0 32 32" fill="none" class="h-8 w-8 shrink-0" aria-hidden="true">
        <g stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 29 C10 21 15 13 25 4" />
            <path d="M10.5 21.5 C6 20 4.5 15.5 6 12.5 C9.5 14 11.5 17.5 10.5 21.5 Z" />
            <path d="M13.5 16.5 C10 13.5 10 9 12.5 6.5 C15 9.5 15.5 13 13.5 16.5 Z" />
            <path d="M17 12.5 C17.5 8.5 20.5 5.5 24 5 C23.5 8.5 20.5 11.5 17 12.5 Z" />
            <path d="M12 19.5 C15.5 21 20 20.5 23 17.5 C19.5 15.5 15 16.5 12 19.5 Z" />
        </g>
    </svg>
    <span class="leading-none">
        <span class="mk-serif block text-[1.65rem] text-ink">{{ config('app.name') }}</span>
        @if ($tagline)
            <span class="mt-1 hidden whitespace-nowrap text-[0.55rem] font-medium uppercase tracking-[0.32em] text-muted sm:block">{{ __('Digital Invitation') }}</span>
        @endif
    </span>
</span>
