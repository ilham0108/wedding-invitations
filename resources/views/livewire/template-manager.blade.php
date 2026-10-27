<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-muted">{{ __('New templates start as drafts. Editing a template only affects invitations created afterwards; existing invitations keep their own copy.') }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-paper"
                title="{{ __('Import a template exported as .zip') }}">
                <i class="fa-solid fa-file-import" aria-hidden="true" wire:loading.remove wire:target="package"></i>
                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true" wire:loading wire:target="package"></i>
                {{ __('Import') }}
                <input type="file" accept=".zip,application/zip" wire:model="package" class="sr-only">
            </label>
            <x-ui.button type="button" icon="fa-solid fa-plus" wire:click="create">{{ __('New template') }}</x-ui.button>
        </div>
    </div>

    @error('package')
        <p class="rounded-lg bg-red-50 p-3 text-sm font-medium text-red-700" role="alert">{{ $message }}</p>
    @enderror

    <div class="grid gap-3 sm:grid-cols-[1fr_12rem_10rem]">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by name or slug…') }}" aria-label="{{ __('Search') }}"
            class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
        <select wire:model.live="category" aria-label="{{ __('Category') }}" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            <option value="">{{ __('All categories') }}</option>
            @foreach ($categories as $categoryOption)
                <option value="{{ $categoryOption->slug }}">{{ $categoryOption->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="status" aria-label="{{ __('Status') }}" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            <option value="">{{ __('All statuses') }}</option>
            @foreach ($statuses as $statusOption)
                <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
            @endforeach
        </select>
    </div>

    @if ($templates->isEmpty())
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-solid fa-swatchbook" :title="__('No templates found.')" :description="__('Create a template or change the filters.')" />
        </x-ui.card>
    @else
        <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($templates as $template)
                <li wire:key="admin-template-{{ $template->ulid }}" class="flex flex-col">
                    <x-template-card :template="$template" :class="$template->isPublished() ? 'flex-1' : 'flex-1 opacity-70'" />

                    <div class="mt-2 flex flex-wrap items-center gap-1.5 px-1">
                        <x-ui.badge :variant="$template->isPublished() ? 'success' : 'neutral'">{{ $template->status->label() }}</x-ui.badge>
                        <span class="text-xs text-muted">{{ trans_choice(':count invitation|:count invitations', $template->invitations_count) }}</span>
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-1 px-1">
                        <x-ui.button :href="route('admin.templates.builder', $template->ulid)" size="sm" icon="fa-solid fa-pen-ruler">{{ __('Builder') }}</x-ui.button>
                        <x-ui.button type="button" variant="secondary" size="sm" wire:click="edit('{{ $template->ulid }}')">{{ __('Details') }}</x-ui.button>
                        @if ($template->isPublished())
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="unpublish('{{ $template->ulid }}')"
                                data-confirm="{{ __('Unpublish this template? Clients can no longer choose it.') }}">{{ __('Unpublish') }}</x-ui.button>
                        @else
                            <x-ui.button type="button" variant="ghost" size="sm" wire:click="publish('{{ $template->ulid }}')">{{ __('Publish') }}</x-ui.button>
                        @endif
                        <button type="button" wire:click="duplicate('{{ $template->ulid }}')" class="rounded p-1.5 text-muted hover:bg-sage-soft hover:text-ink" aria-label="{{ __('Duplicate') }}" title="{{ __('Duplicate') }}"><i class="fa-regular fa-copy" aria-hidden="true"></i></button>
                        <a href="{{ route('admin.templates.export', $template->ulid) }}" class="rounded p-1.5 text-muted hover:bg-sage-soft hover:text-ink" aria-label="{{ __('Export (.zip)') }}" title="{{ __('Export (.zip)') }}"><i class="fa-solid fa-file-export" aria-hidden="true"></i></a>
                        <button type="button" wire:click="remove('{{ $template->ulid }}')" data-confirm="{{ __('Delete this template?') }}" class="ml-auto rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}" title="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($showForm)
        @teleport('body')
        <div class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('showForm', false)">
            <div class="fixed inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative max-h-[90vh] w-full max-w-2xl space-y-5 overflow-y-auto rounded-xl bg-white p-6 shadow-soft">
                <h2 class="text-lg font-semibold text-ink">{{ $editingUlid ? __('Edit template') : __('New template') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="form.name" for="template_name" :label="__('Name')">
                        <input id="template_name" type="text" wire:model.blur="form.name" maxlength="120" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                    <x-ui.field name="form.slug" for="template_slug" label="Slug" :hint="$editingUlid ? __('Changing the slug breaks links to the old preview page.') : __('Lowercase, used in the preview link.')">
                        <input id="template_slug" type="text" wire:model="form.slug" maxlength="100" class="block w-full rounded-lg border-line font-mono text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="form.category" for="template_category" :label="__('Category')">
                        <select id="template_category" wire:model="form.category" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                            @foreach ($categories as $categoryOption)
                                <option value="{{ $categoryOption->slug }}">{{ $categoryOption->name }}@unless ($categoryOption->is_active) ({{ __('inactive') }})@endunless</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                    <x-ui.field name="form.sort_order" for="template_sort" :label="__('Display order')">
                        <input id="template_sort" type="number" min="0" wire:model="form.sort_order" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>

                <x-ui.field name="form.description" for="template_description" :label="__('Description')">
                    <textarea id="template_description" wire:model="form.description" rows="2" maxlength="500" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage"></textarea>
                </x-ui.field>

                <label class="flex items-center gap-2 text-sm text-ink-soft">
                    <input type="checkbox" wire:model="form.is_premium" class="rounded border-line text-sage focus:ring-sage">
                    {{ __('Premium template (only for plans with premium templates)') }}
                </label>

                <x-ui.field name="thumbnail" for="template_thumbnail" :label="__('Thumbnail')" :hint="__('Portrait 4:5 image, PNG, JPG, or WebP up to 5 MB. Without a thumbnail, a preview in the template colors is shown.')">
                    <x-ui.upload-progress>
                    <div class="flex items-center gap-4">
                        @if ($thumbnail)
                            <img src="{{ $thumbnail->temporaryUrl() }}" alt="" class="h-24 w-20 rounded-lg border border-line object-cover">
                        @elseif ($editing?->thumbnailUrl())
                            <img src="{{ $editing->thumbnailUrl() }}" alt="" class="h-24 w-20 rounded-lg border border-line object-cover">
                        @endif
                        <div class="space-y-2">
                            <input id="template_thumbnail" type="file" accept="image/png,image/jpeg,image/webp" wire:model="thumbnail"
                                class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-sage-soft">
                            @if ($thumbnail || $editing?->thumbnail)
                                <button type="button" wire:click="removeThumbnail" data-confirm="{{ __('Remove the thumbnail?') }}" class="text-xs text-red-600 hover:underline">{{ __('Remove thumbnail') }}</button>
                            @endif
                        </div>
                    </div>
                    </x-ui.upload-progress>
                </x-ui.field>

                @unless ($editingUlid)
                    <p class="rounded-lg bg-paper p-3 text-xs text-ink-soft">{{ __('The template starts with Cover, Couple, Event, RSVP, and Closing sections in the Elegant theme. You can change its structure in the template builder.') }}</p>
                @endunless

                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="$set('showForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button wire:loading.attr="disabled" wire:target="save,thumbnail">{{ __('Save template') }}</x-ui.button>
                </div>
            </form>
        </div>
        @endteleport
    @endif
</div>
