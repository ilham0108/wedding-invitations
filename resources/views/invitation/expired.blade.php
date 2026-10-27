<x-layouts.base :title="$title">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 text-center">
        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-accent-soft text-xl text-accent">
            <i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i>
        </span>
        <h1 class="mt-5 font-display text-3xl text-ink">{{ __('This invitation has expired.') }}</h1>
        <p class="mt-2 max-w-sm text-muted">{{ __('The event ":title" is no longer available. Thank you for celebrating with us.', ['title' => $title]) }}</p>
        <x-ui.button :href="route('home')" variant="secondary" class="mt-8">{{ __('Visit :app', ['app' => config('app.name')]) }}</x-ui.button>
    </div>
</x-layouts.base>
