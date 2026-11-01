@php
    $input = 'block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage';
    $sample = 'Ana & Budi · 14 Desember';
@endphp

<div class="space-y-6">
    {{-- Every font previewed on this page (built-in and installed) comes from one stylesheet. --}}
    <link rel="stylesheet" href="{{ $previewStylesheet }}">

    <x-ui.card :title="__('Install a font')" :description="__('Installed fonts can be chosen as heading or body font in the template builder and the invitation editor.')">
        <div class="mb-5 inline-flex rounded-lg border border-line p-0.5 text-sm" role="tablist" aria-label="{{ __('Font source') }}">
            @foreach (['bunny' => __('From Bunny Fonts'), 'upload' => __('Upload files')] as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $mode === $key ? 'true' : 'false' }}" wire:click="$set('mode', '{{ $key }}')"
                    @class(['rounded-md px-3 py-1.5', 'bg-ink text-white' => $mode === $key, 'text-ink-soft hover:bg-paper' => $mode !== $key])>{{ $label }}</button>
            @endforeach
        </div>

        @if ($mode === 'bunny')
            <form wire:submit="lookup" class="max-w-xl space-y-4">
                <x-ui.field name="bunnyName" for="bunny_name" :label="__('Font name')"
                    :hint="__('Type the name exactly as on fonts.bunny.net, e.g. Cormorant Infant or Josefin Sans. These fonts are free to use.')">
                    <div class="flex gap-2">
                        <input id="bunny_name" type="text" wire:model="bunnyName" maxlength="60" autocomplete="off" class="{{ $input }}">
                        <x-ui.button type="submit" variant="secondary" wire:loading.attr="disabled" wire:target="lookup">{{ __('Find') }}</x-ui.button>
                    </div>
                </x-ui.field>
            </form>

            @if ($bunnyMatch)
                <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $bunnyMatch['family'] }}&display=swap">
                <div class="mt-5 max-w-xl rounded-xl border border-line p-4">
                    <p class="text-3xl leading-tight text-ink" style="font-family: '{{ $bunnyMatch['name'] }}', serif;">{{ $sample }}</p>
                    <p class="mt-2 text-sm text-ink-soft">{{ $bunnyMatch['name'] }} · {{ __('weights :weights', ['weights' => implode(', ', $bunnyMatch['weights'])]) }}</p>
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div>
                            <label for="bunny_category" class="block text-xs font-medium text-ink">{{ __('Category') }}</label>
                            <select id="bunny_category" wire:model="bunnyCategory" class="mt-1 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ __(ucfirst($category)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-ui.button type="button" wire:click="addBunny" icon="fa-solid fa-plus">{{ __('Install font') }}</x-ui.button>
                    </div>
                </div>
            @endif
        @else
            <x-ui.upload-progress>
                <form wire:submit="saveUpload" class="max-w-3xl space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field name="uploadName" for="upload_name" :label="__('Font name')" :hint="__('Shown in the theme settings. Letters, digits, spaces and hyphens.')">
                            <input id="upload_name" type="text" wire:model="uploadName" maxlength="60" class="{{ $input }}">
                        </x-ui.field>
                        <x-ui.field name="uploadCategory" for="upload_category" :label="__('Category')">
                            <select id="upload_category" wire:model="uploadCategory" class="{{ $input }}">
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}">{{ __(ucfirst($category)) }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    </div>

                    <x-ui.field name="files" for="upload_files" :label="__('Font files')"
                        :hint="__('WOFF2, WOFF, TTF or OTF, up to :size MB each, at most :count files: one per weight and style (e.g. Regular, Bold, Italic).', ['size' => $maxFileMb, 'count' => $maxFiles])">
                        <input id="upload_files" type="file" multiple accept=".woff2,.woff,.ttf,.otf" wire:model="files"
                            class="block w-full text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-sage-soft">
                    </x-ui.field>
                    @error('files.*')<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror

                    @if ($files !== [])
                        <ul class="divide-y divide-line rounded-xl border border-line">
                            @foreach ($files as $index => $file)
                                <li wire:key="font-upload-{{ $index }}" class="flex flex-wrap items-center gap-3 px-4 py-2.5 text-sm">
                                    <span class="min-w-0 basis-full truncate text-ink sm:basis-0 sm:flex-1">{{ $file->getClientOriginalName() }}</span>
                                    <label class="flex items-center gap-2 text-xs text-ink-soft">{{ __('Weight') }}
                                        <select wire:model="fileWeights.{{ $index }}" class="rounded-md border-line py-1 text-xs focus:border-sage focus:ring-sage">
                                            @foreach ($weights as $weight)
                                                <option value="{{ $weight }}">{{ $weight }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="flex items-center gap-2 text-xs text-ink-soft">{{ __('Style') }}
                                        <select wire:model="fileStyles.{{ $index }}" class="rounded-md border-line py-1 text-xs focus:border-sage focus:ring-sage">
                                            <option value="normal">{{ __('Normal') }}</option>
                                            <option value="italic">{{ __('Italic') }}</option>
                                        </select>
                                    </label>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.button type="submit" icon="fa-solid fa-upload" wire:loading.attr="disabled" wire:target="files,saveUpload">{{ __('Upload and install') }}</x-ui.button>
                        <p class="text-xs text-muted">{{ __('Only upload fonts whose license allows use on websites.') }}</p>
                    </div>
                </form>
            </x-ui.upload-progress>
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Installed fonts')" padding="p-0">
        @if ($installed->isEmpty())
            <x-ui.empty-state icon="fa-solid fa-font" :title="__('No fonts installed yet.')" :description="__('The built-in fonts below are always available.')" />
        @else
            <ul class="divide-y divide-line">
                @foreach ($installed as $font)
                    <li wire:key="font-{{ $font->ulid }}" @class(['flex flex-wrap items-center gap-x-6 gap-y-3 px-5 py-4', 'opacity-60' => ! $font->is_active])>
                        <div class="min-w-0 basis-full sm:basis-0 sm:flex-1">
                            <p class="truncate text-2xl leading-tight text-ink" style="font-family: '{{ $font->name }}', {{ $font->fallback }};">{{ $sample }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted">
                                <span class="font-medium text-ink">{{ $font->name }}</span>
                                <span>{{ __(ucfirst($font->category)) }}</span>
                                <span>·</span>
                                <span>{{ $font->source === \App\Models\Font::SOURCE_BUNNY ? 'Bunny Fonts' : trans_choice(':count uploaded file|:count uploaded files', $font->files_count) }}</span>
                                <span>·</span>
                                <span>{{ $usage[$font->ulid] > 0 ? trans_choice('Used by :count template or invitation|Used by :count templates or invitations', $usage[$font->ulid]) : __('Not used yet') }}</span>
                                @unless ($font->is_active)<x-ui.badge>{{ __('Hidden') }}</x-ui.badge>@endunless
                            </p>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <button type="button" wire:click="toggleActive('{{ $font->ulid }}')" class="inline-flex min-h-11 items-center text-ink-soft hover:text-ink">{{ $font->is_active ? __('Hide') : __('Show') }}</button>
                            @if ($usage[$font->ulid] === 0)
                                <button type="button" wire:click="remove('{{ $font->ulid }}')" data-confirm="{{ __('Delete this font and its files?') }}"
                                    class="inline-flex min-h-11 items-center gap-1.5 text-red-700 hover:underline"><i class="fa-regular fa-trash-can" aria-hidden="true"></i>{{ __('Delete') }}</button>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($installed->hasPages())
                <div class="border-t border-line px-4 py-3">{{ $installed->links() }}</div>
            @endif
        @endif
    </x-ui.card>

    <x-ui.card :title="__('Built-in fonts')" :description="__('Always available; they cannot be changed or removed.')">
        <div class="grid gap-x-8 gap-y-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($builtin as $category => $fonts)
                <div>
                    <p class="text-xs font-medium text-muted">{{ __(ucfirst($category)) }}</p>
                    <ul class="mt-2 space-y-2">
                        @foreach ($fonts as $font)
                            <li>
                                <span class="block truncate text-lg leading-snug text-ink" style="font-family: '{{ $font['name'] }}', {{ $font['fallback'] }};">{{ $font['name'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-ui.card>
</div>
