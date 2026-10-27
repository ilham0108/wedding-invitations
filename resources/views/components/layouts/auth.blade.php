@props(['title', 'subtitle' => null, 'showStatus' => true])

<x-layouts.base :title="$title">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <a href="{{ route('home') }}" class="mb-8 flex items-center" aria-label="{{ config('app.name') }}"><x-logo height="h-20" /></a>

        <div class="w-full max-w-md rounded-xl border border-line bg-white p-6 shadow-soft sm:p-8">
            <h1 class="font-display text-2xl text-ink">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>
            @endif

            @if ($showStatus && session('status'))
                <div class="mt-4 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ session('status') }}</div>
            @endif

            <div class="mt-6">
                {{ $slot }}
            </div>
        </div>

        {{ $footer ?? '' }}
    </div>
</x-layouts.base>
