{{--
    A collapsible group of the template builder's side panel. The open state lives in the panel's Alpine data
    ("open", keyed by $id; every group starts collapsed) so it survives Livewire updates and stays the same when another section is selected;
    wire:ignore.self keeps Livewire from resetting the toggled attributes. The wire:key makes Livewire replace, not
    reuse, a group when another one takes its place (e.g. switching to the Theme tab), since ignored attributes
    such as the id would otherwise stay those of the old group.
--}}
@props(['id', 'title', 'icon', 'count' => null])

<section wire:key="builder-group-{{ $id }}" class="overflow-hidden rounded-xl border border-line bg-white">
    <h3>
        <button type="button" wire:ignore.self x-on:click="toggle('{{ $id }}')" x-bind:aria-expanded="open['{{ $id }}'] ? 'true' : 'false'" aria-controls="builder-group-{{ $id }}"
            class="flex min-h-11 w-full cursor-pointer items-center gap-2.5 bg-sage-soft px-3.5 py-2.5 text-left text-sm font-semibold text-ink transition-colors hover:bg-[#dbe3de] active:bg-[#cfd9d3] focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sage">
            <i class="{{ $icon }} w-4 shrink-0 text-center text-sage" aria-hidden="true"></i>
            <span class="min-w-0 flex-1">{{ $title }}@if ($count !== null) <span class="font-normal text-ink-soft">({{ $count }})</span>@endif</span>
            <i wire:ignore.self class="fa-solid fa-chevron-down shrink-0 text-xs text-ink-soft transition-transform motion-reduce:transition-none" x-bind:class="! open['{{ $id }}'] && '-rotate-90'" aria-hidden="true"></i>
        </button>
    </h3>
    <div id="builder-group-{{ $id }}" wire:ignore.self x-show="open['{{ $id }}']" x-cloak class="space-y-4 border-t border-line p-3.5">
        {{ $slot }}
    </div>
</section>
