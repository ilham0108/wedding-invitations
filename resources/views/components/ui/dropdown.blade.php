@props(['align' => 'right', 'width' => 'w-56'])

<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" x-on:keydown.escape.window="open = false">
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-cloak x-show="open" x-transition.origin.top
        class="absolute z-40 mt-2 {{ $width }} {{ $align === 'left' ? 'left-0' : 'right-0' }} rounded-xl border border-line bg-white py-1.5 shadow-soft">
        {{ $slot }}
    </div>
</div>
