<div>
    <div class="mb-2 flex items-center justify-between px-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-muted">{{ __('Sections') }}</p>

        <x-ui.dropdown align="right" width="w-56">
            <x-slot:trigger>
                <button type="button" class="rounded-md px-2 py-1 text-xs font-medium text-sage hover:bg-sage-soft">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('Add') }}
                </button>
            </x-slot:trigger>
            <div class="max-h-72 overflow-y-auto">
                @foreach ($addableTypes as $type => $label)
                    <button type="button" wire:click="add('{{ $type }}')" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-ink-soft hover:bg-paper">
                        <i class="{{ $registry->definition($type)['icon'] }} w-4 text-center text-muted" aria-hidden="true"></i>{{ __($label) }}
                    </button>
                @endforeach
            </div>
        </x-ui.dropdown>
    </div>

    @if ($sections->isEmpty())
        <p class="px-1 py-6 text-center text-sm text-muted">{{ __('No sections yet. Add one to get started.') }}</p>
    @endif

    <ul wire:sort="reorder" class="space-y-1">
        @foreach ($sections as $section)
            <li wire:key="section-{{ $section->ulid }}" wire:sort:item="{{ $section->ulid }}"
                @class([
                    'group flex items-center gap-2 rounded-lg border px-2 py-2 text-sm transition',
                    'border-ink/20 bg-white shadow-soft' => $selected === $section->ulid,
                    'border-transparent hover:bg-white' => $selected !== $section->ulid,
                    'opacity-50' => ! $section->is_enabled,
                ])>
                <span wire:sort:handle class="cursor-grab px-1 text-muted active:cursor-grabbing" title="{{ __('Drag to reorder') }}">
                    <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                </span>

                <button type="button" wire:click="select('{{ $section->ulid }}')" class="min-w-0 flex-1 text-left">
                    <span class="block truncate font-medium text-ink">
                        <i class="{{ $registry->definition($section->type)['icon'] }} mr-1 w-4 text-center text-muted" aria-hidden="true"></i>{{ __($registry->label($section->type)) }}
                    </span>
                    @if (filled($section->configuration['heading'] ?? $section->configuration['title'] ?? null))
                        <span class="block truncate pl-5 text-xs text-muted">{{ $section->configuration['heading'] ?? $section->configuration['title'] }}</span>
                    @endif
                </button>

                <x-ui.dropdown width="w-44">
                    <x-slot:trigger>
                        <button type="button" class="rounded p-1 text-muted hover:text-ink" aria-label="{{ __('Section actions') }}">
                            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                        </button>
                    </x-slot:trigger>
                    <button type="button" wire:click="toggle('{{ $section->ulid }}')" class="block w-full px-4 py-2 text-left text-sm text-ink-soft hover:bg-paper">
                        {{ $section->is_enabled ? __('Disable') : __('Enable') }}
                    </button>
                    <button type="button" wire:click="duplicate('{{ $section->ulid }}')" class="block w-full px-4 py-2 text-left text-sm text-ink-soft hover:bg-paper">{{ __('Duplicate') }}</button>
                    <button type="button" wire:click="remove('{{ $section->ulid }}')" data-confirm="{{ __('Delete this section?') }}"
                        class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">{{ __('Delete') }}</button>
                </x-ui.dropdown>
            </li>
        @endforeach
    </ul>
</div>
