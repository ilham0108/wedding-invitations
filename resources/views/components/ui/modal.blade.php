@props(['name', 'show' => false, 'maxWidth' => 'max-w-lg'])

<div
    x-data="{ show: @js($show) }"
    x-on:open-modal.window="$event.detail === '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail === '{{ $name }}' ? show = false : null"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
    role="dialog"
    aria-modal="true"
>
    <div class="fixed inset-0 bg-ink/40" x-show="show" x-transition.opacity x-on:click="show = false"></div>

    <div x-show="show" x-transition
        class="relative w-full {{ $maxWidth }} rounded-xl bg-white shadow-soft">
        {{ $slot }}
    </div>
</div>
