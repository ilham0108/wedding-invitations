{{--
    Import progress (importPhase): "upload" with its percentage; at 100% the file is on the server and "process" shows
    while TemplateManager::updatedPackage checks it and copies its images and music. Livewire only fires
    livewire-upload-finish after that has run, so finish/error end it (and "template-import-finished", as a safeguard).
--}}
<div class="space-y-6" x-data="{ importPhase: null, importProgress: 0 }"
    x-on:livewire-upload-start="if ($event.target.name === 'package') { importPhase = 'upload'; importProgress = 0 }"
    x-on:livewire-upload-progress="if ($event.target.name === 'package') { importProgress = $event.detail.progress; if (importProgress >= 100) importPhase = 'process' }"
    x-on:livewire-upload-finish="if ($event.target.name === 'package') importPhase = null"
    x-on:livewire-upload-error="if ($event.target.name === 'package') importPhase = null"
    x-on:livewire-upload-cancel="if ($event.target.name === 'package') importPhase = null"
    x-on:template-import-finished.window="importPhase = null">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-muted">{{ __('New templates start as drafts. Editing a template only affects invitations created afterwards; existing invitations keep their own copy.') }}</p>
        <div class="flex flex-wrap items-center gap-2">
            <label x-bind:class="importPhase && 'pointer-events-none opacity-60'"
                class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-paper"
                title="{{ __('Import a template exported as .zip') }}">
                <i class="fa-solid fa-file-import" aria-hidden="true"></i>
                {{ __('Import') }}
                <input type="file" name="package" accept=".zip,application/zip" wire:model="package" class="sr-only">
            </label>
            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.templates.import-html') }}" class="inline-flex items-center gap-2 rounded-lg border border-line bg-white px-4 py-2 text-sm font-medium text-ink hover:bg-paper">
                    <i class="fa-solid fa-code" aria-hidden="true"></i>{{ __('Import HTML') }}
                </a>
            @endif
            <x-ui.button type="button" icon="fa-solid fa-plus" wire:click="create">{{ __('New template') }}</x-ui.button>
        </div>
    </div>

    <div x-show="importPhase" x-cloak role="status" aria-live="polite" class="rounded-lg border border-line bg-white p-4">
        <div class="flex items-center justify-between text-sm">
            <span class="flex items-center gap-2 font-medium text-ink">
                <i class="fa-solid fa-spinner fa-spin text-sage" aria-hidden="true"></i>
                <span x-text="importPhase === 'upload' ? @js(__('Uploading the template…')) : @js(__('Importing the template: checking the file and copying its images and music…'))"></span>
            </span>
            <span class="tabular-nums text-muted" x-show="importPhase === 'upload'" x-text="importProgress + '%'"></span>
        </div>
        <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-line">
            <div x-show="importPhase === 'upload'" class="h-full rounded-full bg-sage transition-[width] duration-200"
                x-bind:style="'width: ' + importProgress + '%'"></div>
            <div x-show="importPhase === 'process'" class="h-full w-1/3 rounded-full bg-sage motion-safe:animate-[import-slide_1.2s_ease-in-out_infinite]"></div>
        </div>
        <p class="mt-2 text-xs text-muted">{{ __('Keep this page open until the import has finished.') }}</p>
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
        <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
            @foreach ($templates as $template)
                <li wire:key="admin-template-{{ $template->ulid }}" class="flex flex-col">
                    <x-template-card :template="$template" :class="$template->isPublished() ? 'flex-1' : 'flex-1 opacity-70'" />

                    {{-- Published is the normal state: only drafts and unsaved builder work are flagged. --}}
                    <div class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 px-1 text-xs">
                        @unless ($template->isPublished())
                            <x-ui.badge>{{ $template->status->label() }}</x-ui.badge>
                        @endunless
                        @if (in_array($template->ulid, $unsaved, true))
                            <span class="inline-flex items-center gap-1 font-medium text-amber-800" title="{{ __('Open the builder and save the template to keep them.') }}">
                                <i class="fa-solid fa-circle text-[6px]" aria-hidden="true"></i>{{ __('Unsaved changes') }}
                            </span>
                        @endif
                        <span class="text-muted">{{ trans_choice(':count invitation|:count invitations', $template->invitations_count) }}</span>
                    </div>

                    {{-- The two everyday actions stay visible; the rest sit in one menu. --}}
                    <div class="mt-2 flex items-center gap-1.5 px-1">
                        <x-ui.button :href="route('admin.templates.builder', $template->ulid)" size="sm" icon="fa-solid fa-pen-ruler">{{ __('Builder') }}</x-ui.button>
                        <x-ui.button type="button" variant="secondary" size="sm" wire:click="edit('{{ $template->ulid }}')">{{ __('Details') }}</x-ui.button>
                        <x-ui.dropdown width="w-52">
                            <x-slot:trigger>
                                <button type="button" class="flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-sm text-ink-soft hover:bg-sage-soft hover:text-ink" x-bind:aria-expanded="open.toString()" aria-haspopup="true">
                                    {{ __('More') }}<i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
                                </button>
                            </x-slot:trigger>
                            @if ($template->isPublished())
                                <button type="button" wire:click="unpublish('{{ $template->ulid }}')" data-confirm="{{ __('Unpublish this template? Clients can no longer choose it.') }}"
                                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-ink hover:bg-paper"><i class="fa-regular fa-eye-slash w-4 text-muted" aria-hidden="true"></i>{{ __('Unpublish') }}</button>
                            @else
                                <button type="button" wire:click="publish('{{ $template->ulid }}')"
                                    class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-ink hover:bg-paper"><i class="fa-regular fa-eye w-4 text-muted" aria-hidden="true"></i>{{ __('Publish') }}</button>
                            @endif
                            <button type="button" wire:click="duplicate('{{ $template->ulid }}')"
                                class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm text-ink hover:bg-paper"><i class="fa-regular fa-copy w-4 text-muted" aria-hidden="true"></i>{{ __('Duplicate') }}</button>
                            <a href="{{ route('admin.templates.export', $template->ulid) }}" class="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-ink hover:bg-paper"
                                @if (in_array($template->ulid, $unsaved, true)) data-confirm="{{ __('This template has builder changes that are not saved yet. The export only contains the saved version. Export anyway?') }}" data-confirm-button="{{ __('Export saved version') }}" data-confirm-variant="default" @endif><i class="fa-solid fa-file-export w-4 text-muted" aria-hidden="true"></i>{{ __('Export (.zip)') }}</a>
                            <button type="button" wire:click="remove('{{ $template->ulid }}')" data-confirm="{{ __('Delete this template?') }}"
                                class="flex w-full items-center gap-2.5 border-t border-line px-4 py-2 text-left text-sm text-red-700 hover:bg-red-50"><i class="fa-regular fa-trash-can w-4" aria-hidden="true"></i>{{ __('Delete') }}</button>
                        </x-ui.dropdown>
                    </div>
                </li>
            @endforeach
        </ul>
        @if ($templates->hasPages())
            <div class="mt-6">{{ $templates->links() }}</div>
        @endif
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
