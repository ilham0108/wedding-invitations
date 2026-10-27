<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-muted">{{ __('Each category belongs to an event type. When clients pick that event type in the creation wizard, they see the published templates of its active categories.') }}</p>
        <x-ui.button type="button" icon="fa-solid fa-plus" wire:click="create">{{ __('New category') }}</x-ui.button>
    </div>

    <x-ui.card padding="p-0">
        @if ($categories->isEmpty())
            <x-ui.empty-state icon="fa-solid fa-tags" :title="__('No categories yet.')" />
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-line text-sm">
                    <thead class="bg-paper text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Category') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Event type') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Templates') }}</th>
                            <th scope="col" class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($categories as $category)
                            <tr wire:key="category-{{ $category->slug }}" @class(['opacity-60' => ! $category->is_active])>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-sage-soft text-sage"><i class="{{ $category->icon ?: 'fa-solid fa-tag' }}" aria-hidden="true"></i></span>
                                        <div class="min-w-0">
                                            <p class="font-medium text-ink">{{ $category->name }}</p>
                                            <p class="font-mono text-xs text-muted">{{ $category->slug }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-ink-soft">{{ $category->event_type->label() }}</td>
                                <td class="px-4 py-3 text-ink-soft">{{ __(':published of :total published', ['published' => $category->published_templates_count, 'total' => $category->templates_count]) }}</td>
                                <td class="px-4 py-3">
                                    <button type="button" wire:click="toggleActive('{{ $category->slug }}')" title="{{ $category->is_active ? __('Deactivate') : __('Activate') }}">
                                        <x-ui.badge :variant="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                                    </button>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-1">
                                        <x-ui.button type="button" variant="secondary" size="sm" wire:click="edit('{{ $category->slug }}')">{{ __('Edit') }}</x-ui.button>
                                        <button type="button" wire:click="remove('{{ $category->slug }}')" data-confirm="{{ __('Delete this category?') }}" class="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($showForm)
        @teleport('body')
        <div class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('showForm', false)">
            <div class="fixed inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative max-h-[90vh] w-full max-w-lg space-y-5 overflow-y-auto rounded-xl bg-white p-6 shadow-soft">
                <h2 class="text-lg font-semibold text-ink">{{ $editingSlug ? __('Edit category') : __('New category') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="form.name" for="category_name" :label="__('Name')">
                        <input id="category_name" type="text" wire:model.blur="form.name" maxlength="80" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                    <x-ui.field name="form.slug" for="category_slug" label="Slug">
                        <input id="category_slug" type="text" wire:model="form.slug" maxlength="50" class="block w-full rounded-lg border-line font-mono text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                <x-ui.field name="form.event_type" for="category_event_type" :label="__('Event type')" :hint="__('Templates in this category appear when clients create an invitation for this event type.')">
                    <select id="category_event_type" wire:model.live="form.event_type" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                        @foreach ($eventTypes as $eventType)
                            <option value="{{ $eventType->value }}">{{ $eventType->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field name="form.description" for="category_description" :label="__('Description')">
                    <input id="category_description" type="text" wire:model="form.description" maxlength="255" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                </x-ui.field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="form.icon" for="category_icon" :label="__('Icon')" :hint="__('Font Awesome class, e.g. fa-solid fa-ring')">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sage-soft text-sage"><i class="{{ preg_match('/^fa-(solid|regular|brands) fa-[a-z0-9-]+$/', $form['icon']) ? $form['icon'] : 'fa-solid fa-tag' }}" aria-hidden="true"></i></span>
                            <input id="category_icon" type="text" wire:model.blur="form.icon" maxlength="50" class="block w-full rounded-lg border-line font-mono text-sm focus:border-sage focus:ring-sage">
                        </div>
                    </x-ui.field>
                    <x-ui.field name="form.sort_order" for="category_sort" :label="__('Display order')">
                        <input id="category_sort" type="number" min="0" wire:model="form.sort_order" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" wire:model="form.is_active" class="rounded border-line text-sage focus:ring-sage">
                    {{ __('Active (inactive categories hide all their templates from clients)') }}
                </label>

                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="$set('showForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">{{ __('Save category') }}</x-ui.button>
                </div>
            </form>
        </div>
        @endteleport
    @endif
</div>
