<x-layouts.base :title="$title">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-sage-soft text-xl text-sage">
            <i class="fa-regular fa-envelope" aria-hidden="true"></i>
        </span>
        <h1 class="mt-5 font-display text-3xl text-ink">{{ __('This invitation is currently unavailable.') }}</h1>
        <p class="mt-2 max-w-sm text-muted">{{ __('Please check back later or contact the hosts of ":title".', ['title' => $title]) }}</p>
    </div>
</x-layouts.base>
