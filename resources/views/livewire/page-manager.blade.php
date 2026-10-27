@php($input = 'mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage')

<div class="space-y-4">
    @if ($editing)
        <form wire:submit="save" class="max-w-4xl space-y-6">
            <x-ui.card>
                <div class="space-y-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="page-title" class="block text-sm font-medium text-ink">{{ __('Title') }}</label>
                            <input id="page-title" type="text" wire:model.live.debounce.400ms="form.title" class="{{ $input }}">
                            @error('form.title')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="page-slug" class="block text-sm font-medium text-ink">{{ __('Address') }}</label>
                            <div class="mt-1.5 flex items-center rounded-lg border border-line bg-paper text-sm">
                                <span class="pl-3 text-muted">/page/</span>
                                <input id="page-slug" type="text" wire:model="form.slug" class="block w-full border-0 bg-transparent text-sm focus:ring-0">
                            </div>
                            @error('form.slug')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="page-body" class="block text-sm font-medium text-ink">{{ __('Content') }}</label>
                            <div class="flex rounded-lg border border-line p-0.5 text-xs">
                                <button type="button" wire:click="$set('preview', false)" @class(['rounded-md px-2.5 py-1', 'bg-ink text-white' => ! $preview, 'text-ink-soft' => $preview])>{{ __('Write') }}</button>
                                <button type="button" wire:click="$set('preview', true)" @class(['rounded-md px-2.5 py-1', 'bg-ink text-white' => $preview, 'text-ink-soft' => ! $preview])>{{ __('Preview') }}</button>
                            </div>
                        </div>
                        @if ($preview)
                            <div class="cms-prose mt-1.5 min-h-[18rem] rounded-lg border border-line bg-white p-5">{!! $previewHtml !!}</div>
                        @else
                            <textarea id="page-body" rows="18" wire:model="form.body" class="{{ $input }} font-mono"></textarea>
                        @endif
                        <p class="mt-1 text-xs text-muted">{{ __('Markdown: ## Heading, **bold**, *italic*, - list item, [link text](https://…). HTML is not allowed.') }}</p>
                        @error('form.body')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="page-meta" class="block text-sm font-medium text-ink">{{ __('Search engine description (optional)') }}</label>
                        <input id="page-meta" type="text" maxlength="300" wire:model="form.meta_description" class="{{ $input }}">
                    </div>

                    <div class="flex flex-wrap items-center gap-6 text-sm text-ink-soft">
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_published" class="rounded border-line text-sage focus:ring-sage"> {{ __('Published') }}</label>
                        <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.show_in_footer" class="rounded border-line text-sage focus:ring-sage"> {{ __('Show in footer') }}</label>
                        <label class="flex items-center gap-2">{{ __('Order') }} <input type="number" min="0" wire:model="form.sort_order" class="w-20 rounded-lg border-line text-sm focus:border-sage focus:ring-sage"></label>
                    </div>
                </div>
            </x-ui.card>

            <div class="flex justify-end gap-2">
                <x-ui.button type="button" variant="ghost" wire:click="$set('editing', false)">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" icon="fa-regular fa-floppy-disk" wire:loading.attr="disabled" wire:target="save">{{ __('Save page') }}</x-ui.button>
            </div>
        </form>
    @else
        <div class="flex justify-end">
            <x-ui.button type="button" icon="fa-solid fa-plus" wire:click="create">{{ __('New page') }}</x-ui.button>
        </div>

        <x-ui.card padding="p-0">
            @if ($pages->isEmpty())
                <x-ui.empty-state icon="fa-regular fa-file-lines" :title="__('No pages yet.')" :description="__('Add pages such as Terms & Conditions or Privacy Policy.')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($pages as $page)
                        <li wire:key="cms-page-{{ $page->ulid }}" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">{{ $page->title }}</p>
                                <p class="text-xs text-muted">/page/{{ $page->slug }}@if ($page->show_in_footer) · {{ __('in the footer') }}@endif · {{ __('Updated :time', ['time' => $page->updated_at->diffForHumans()]) }}</p>
                            </div>
                            <x-ui.badge :variant="$page->is_published ? 'success' : 'neutral'">{{ $page->is_published ? __('Published') : __('Draft') }}</x-ui.badge>
                            @if ($page->is_published)
                                <x-ui.button :href="route('pages.show', $page)" variant="ghost" size="sm" icon="fa-solid fa-arrow-up-right-from-square" target="_blank">{{ __('Open') }}</x-ui.button>
                            @endif
                            <x-ui.button type="button" variant="secondary" size="sm" wire:click="edit('{{ $page->ulid }}')">{{ __('Edit') }}</x-ui.button>
                            <button type="button" wire:click="remove('{{ $page->ulid }}')" data-confirm="{{ __('Delete this page?') }}" class="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    @endif
</div>
