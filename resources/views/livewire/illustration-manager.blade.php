<div class="space-y-6">
    <x-ui.card :title="__('Upload illustrations')" :description="__('PNG, JPG, or WebP up to 5 MB. Files are resized and converted to WebP; transparent backgrounds are kept.')">
        <x-ui.upload-progress>
            <form wire:submit="saveUploads" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                <x-ui.field name="uploadCategory" for="upload_category" :label="__('Category')">
                    <select id="upload_category" wire:model="uploadCategory" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                        @foreach ($categories as $categoryOption)
                            <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                <x-ui.field name="files" for="upload_files" :label="__('Files')">
                    <input id="upload_files" type="file" multiple accept="image/png,image/jpeg,image/webp" wire:model="files"
                        class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-sage-soft">
                </x-ui.field>

                <x-ui.button wire:loading.attr="disabled" wire:target="files,saveUploads" icon="fa-solid fa-upload">{{ __('Upload') }}</x-ui.button>

                <label class="flex items-center gap-2 text-sm text-ink-soft sm:col-span-3">
                    <input type="checkbox" wire:model="uploadPremium" class="rounded border-line text-sage focus:ring-sage">
                    {{ __('Mark as premium') }}
                    <span class="text-xs text-muted">({{ __('premium restrictions apply once subscription plans are active') }})</span>
                </label>
            </form>
        </x-ui.upload-progress>
        <p wire:loading wire:target="saveUploads" class="mt-2 text-xs text-muted"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Processing and converting to WebP…') }}</p>
        @error('files.*')<p class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
    </x-ui.card>

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('Filter by category') }}">
        <button type="button" wire:click="$set('category', '')"
            @class(['rounded-full border px-3 py-1 text-sm', 'border-ink bg-ink text-white' => $category === '', 'border-line text-ink-soft hover:border-ink/30' => $category !== ''])>
            {{ __('All') }} <span class="opacity-70">{{ $counts->sum() }}</span>
        </button>
        @foreach ($categories as $categoryOption)
            <button type="button" wire:click="$set('category', '{{ $categoryOption->value }}')"
                @class(['rounded-full border px-3 py-1 text-sm', 'border-ink bg-ink text-white' => $category === $categoryOption->value, 'border-line text-ink-soft hover:border-ink/30' => $category !== $categoryOption->value])>
                {{ $categoryOption->label() }} <span class="opacity-70">{{ $counts[$categoryOption->value] ?? 0 }}</span>
            </button>
        @endforeach
    </nav>

    @if ($illustrations->isEmpty())
        <x-ui.card padding="p-0">
            <x-ui.empty-state icon="fa-solid fa-palette" :title="__('No illustrations yet.')" :description="__('Upload 2D art that clients can use instead of photos.')" />
        </x-ui.card>
    @else
        <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            @foreach ($illustrations as $illustration)
                <li wire:key="admin-illustration-{{ $illustration->ulid }}" @class(['overflow-hidden rounded-xl border bg-white', 'border-line' => $illustration->is_active, 'border-dashed border-line opacity-60' => ! $illustration->is_active])>
                    <div class="relative bg-paper">
                        <img src="{{ $illustration->url() }}" alt="" loading="lazy" decoding="async" class="aspect-square w-full object-contain p-3">
                        <div class="absolute left-2 top-2 flex flex-wrap gap-1">
                            @if ($illustration->is_premium)<x-ui.badge variant="warning">{{ __('Premium') }}</x-ui.badge>@endif
                            @unless ($illustration->is_active)<x-ui.badge>{{ __('Hidden') }}</x-ui.badge>@endunless
                        </div>
                    </div>
                    <div class="space-y-2 p-3">
                        <input type="text" value="{{ $illustration->name }}" maxlength="80" aria-label="{{ __('Name') }}"
                            x-on:change="$wire.rename('{{ $illustration->ulid }}', $event.target.value)"
                            class="block w-full rounded-md border-transparent px-1 py-0.5 text-sm font-medium text-ink hover:border-line focus:border-sage focus:ring-sage">
                        <p class="px-1 text-xs text-muted">{{ $illustration->category->label() }}@if ($illustration->isBundled()) · {{ __('Built-in') }}@endif</p>
                        <div class="flex items-center justify-between gap-1 text-xs">
                            <button type="button" wire:click="toggleActive('{{ $illustration->ulid }}')" class="text-ink-soft hover:text-ink">{{ $illustration->is_active ? __('Hide') : __('Show') }}</button>
                            <button type="button" wire:click="togglePremium('{{ $illustration->ulid }}')" class="text-ink-soft hover:text-ink">{{ $illustration->is_premium ? __('Make free') : __('Make premium') }}</button>
                            <button type="button" wire:click="remove('{{ $illustration->ulid }}')" data-confirm="{{ __('Delete this illustration?') }}" class="text-red-600 hover:underline" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
