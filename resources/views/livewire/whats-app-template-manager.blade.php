<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">{{ __('Save messages you send often and reuse them in campaigns.') }}</p>
        <x-ui.button type="button" icon="fa-solid fa-plus" wire:click="create">{{ __('New template') }}</x-ui.button>
    </div>

    <x-ui.card padding="p-0">
        @if ($templates->isEmpty())
            <x-ui.empty-state icon="fa-regular fa-message" :title="__('No message templates yet.')" :description="__('Create one, or write the message directly when creating a campaign.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($templates as $template)
                    <li wire:key="template-{{ $template->ulid }}" class="flex items-start gap-3 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">{{ $template->name }}</p>
                            <p class="mt-1 line-clamp-2 whitespace-pre-line text-sm text-ink-soft">{{ $template->body }}</p>
                        </div>
                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="edit('{{ $template->ulid }}')">{{ __('Edit') }}</x-ui.button>
                        <button type="button" wire:click="delete('{{ $template->ulid }}')" data-confirm="{{ __('Delete this template?') }}"
                            class="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    @if ($showForm)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('showForm', false)">
            <div class="fixed inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative max-h-[90vh] w-full max-w-xl space-y-4 overflow-y-auto rounded-xl bg-white p-6 shadow-soft">
                <h2 class="text-lg font-semibold text-ink">{{ $editingUlid ? __('Edit template') : __('New template') }}</h2>

                <x-ui.field name="name" for="template_name" :label="__('Name')">
                    <input id="template_name" type="text" wire:model="name" maxlength="80" placeholder="{{ __('e.g. Main invitation') }}" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                </x-ui.field>

                <x-ui.field name="body" for="template_body" :label="__('Message')">
                    <x-whatsapp.message-editor model="body" id="template_body" :variables="$variables" />
                </x-ui.field>

                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button type="button" variant="secondary" wire:click="$set('showForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">{{ __('Save template') }}</x-ui.button>
                </div>
            </form>
        </div>
    @endif
</div>
