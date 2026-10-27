@php
    $input = 'mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage';
    // Text fields in display order: key => [label, textarea?, help].
    $textFields = [
        'badge' => [__('Small label above the title'), false, null],
        'eyebrow' => [__('Small label above the heading'), false, null],
        'title' => [__('Title'), false, __('Put a word between *asterisks* to highlight it.')],
        'heading' => [__('Heading'), false, __('Put a word between *asterisks* to highlight it.')],
        'subtitle' => [__('Text under the heading'), true, null],
        'text' => [__('Text'), true, null],
        'secondary_label' => [__('Templates button'), false, null],
        'primary_label' => [__('Sign-up button'), false, null],
        'button_label' => [__('Button label'), false, null],
        'link_label' => [__('"See all" link label'), false, null],
    ];
@endphp

<div class="space-y-6">
    <div class="flex items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-muted">{{ __('Text of the public website. Templates and plan prices are managed on their own pages.') }}</p>
        <x-ui.button :href="route('home')" variant="secondary" size="sm" icon="fa-solid fa-arrow-up-right-from-square" target="_blank">{{ __('View site') }}</x-ui.button>
    </div>

    <nav class="flex flex-wrap gap-1 border-b border-line" aria-label="{{ __('Site sections') }}">
        @foreach ([...$sections, 'pages' => 'Pages'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                @class(['-mb-px border-b-2 px-3 py-2 text-sm', 'border-ink font-medium text-ink' => $tab === $key, 'border-transparent text-muted hover:text-ink' => $tab !== $key])>{{ __($label) }}</button>
        @endforeach
    </nav>

    @if ($tab === 'pages')
        <livewire:page-manager />
    @else
        <form wire:submit="save" class="max-w-3xl space-y-6" wire:key="section-form-{{ $tab }}">
            <x-ui.card>
                <div class="space-y-5">
                    @if ($tab === 'contact')
                        @foreach (['tagline' => [__('Short description in the footer'), 'text'], 'email' => [__('Email'), 'email'], 'whatsapp' => [__('WhatsApp number'), 'text'], 'address' => [__('Address'), 'text'], 'instagram' => ['Instagram URL', 'url'], 'facebook' => ['Facebook URL', 'url'], 'tiktok' => ['TikTok URL', 'url'], 'youtube' => ['YouTube URL', 'url']] as $key => [$label, $type])
                            <div>
                                <label for="cms-{{ $key }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
                                <input id="cms-{{ $key }}" type="{{ $type }}" wire:model="form.{{ $key }}" class="{{ $input }}" @if ($type === 'url') placeholder="https://" @endif>
                                @error("form.{$key}")<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                        <p class="text-xs text-muted">{{ __('Leave a field empty to hide it. Published pages marked "Show in footer" are linked there too.') }}</p>
                    @else
                        @foreach ($textFields as $key => [$label, $multiline, $help])
                            @continue(! array_key_exists($key, $form))
                            <div>
                                <label for="cms-{{ $key }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
                                @if ($multiline)
                                    <textarea id="cms-{{ $key }}" rows="3" wire:model="form.{{ $key }}" class="{{ $input }}"></textarea>
                                @else
                                    <input id="cms-{{ $key }}" type="text" wire:model="form.{{ $key }}" class="{{ $input }}">
                                @endif
                                @if ($help)<p class="mt-1 text-xs text-muted">{{ $help }}</p>@endif
                                @error("form.{$key}")<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endforeach

                        @if (array_key_exists('image', $form))
                            <div>
                                <p class="text-sm font-medium text-ink">{{ __('Picture') }}</p>
                                <p class="mt-0.5 text-xs text-muted">{{ $imageHelp }} {{ __('PNG, JPG or WebP.') }}</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    @if ($form['image'])
                                        <img src="{{ $form['image'] }}" alt="" class="h-24 w-36 rounded-lg border border-line object-cover">
                                        <x-ui.button type="button" variant="ghost" size="sm" class="text-red-600" wire:click="removeSectionImage">{{ __('Remove') }}</x-ui.button>
                                    @endif
                                    <x-ui.upload-progress>
                                        <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="sectionImage" aria-label="{{ __('Upload picture') }}"
                                            class="block text-sm text-ink-soft file:mr-3 file:rounded-lg file:border-0 file:bg-paper file:px-3 file:py-2 file:text-sm file:font-medium file:text-ink hover:file:bg-sage-soft">
                                    </x-ui.upload-progress>
                                </div>
                                @error('sectionImage')<p class="mt-1 text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                            </div>
                        @endif

                        @if (array_key_exists('items', $form))
                            <div>
                                <p class="text-sm font-medium text-ink">{{ $tab === 'faq' ? __('Questions') : __('Items') }}</p>
                                @if ($tab === 'faq' && $form['items'] === [])
                                    <p class="mt-1 text-xs text-muted">{{ __('The FAQ section stays hidden on the site until it has at least one question.') }}</p>
                                @endif
                                <ol class="mt-2 space-y-2">
                                    @foreach ($form['items'] as $index => $item)
                                        <li wire:key="cms-item-{{ $tab }}-{{ $index }}" class="rounded-lg border border-line p-3">
                                            <div class="flex items-start gap-2">
                                                <div class="min-w-0 flex-1 space-y-2">
                                                    @if ($tab === 'faq')
                                                        <input type="text" wire:model="form.items.{{ $index }}.question" placeholder="{{ __('Question') }}" aria-label="{{ __('Question') }}" class="block w-full rounded-lg border-line text-sm font-medium focus:border-sage focus:ring-sage">
                                                        <textarea rows="2" wire:model="form.items.{{ $index }}.answer" placeholder="{{ __('Answer') }}" aria-label="{{ __('Answer') }}" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage"></textarea>
                                                        @error("form.items.{$index}.question")<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                                                        @error("form.items.{$index}.answer")<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                                                    @else
                                                        <div class="flex items-center gap-2">
                                                            <i class="{{ $item['icon'] ?? '' }} w-5 text-center text-sage" aria-hidden="true"></i>
                                                            <select wire:model.live="form.items.{{ $index }}.icon" aria-label="{{ __('Icon') }}" class="w-44 rounded-lg border-line text-xs focus:border-sage focus:ring-sage">
                                                                @foreach ($icons as $icon)
                                                                    <option value="{{ $icon }}">{{ str($icon)->afterLast(' fa-')->replace('-', ' ') }}</option>
                                                                @endforeach
                                                            </select>
                                                            <input type="text" wire:model="form.items.{{ $index }}.title" aria-label="{{ __('Title') }}" class="min-w-0 flex-1 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                                                        </div>
                                                        @if ($described)
                                                            <input type="text" wire:model="form.items.{{ $index }}.description" placeholder="{{ __('Short description (optional)') }}" aria-label="{{ __('Description') }}" class="block w-full rounded-lg border-line text-xs focus:border-sage focus:ring-sage">
                                                            @error("form.items.{$index}.description")<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                                                        @endif
                                                        @error("form.items.{$index}.title")<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                                                        @error("form.items.{$index}.icon")<p class="text-sm font-medium text-red-600">{{ $message }}</p>@enderror
                                                    @endif
                                                </div>
                                                <div @class(['flex shrink-0', 'flex-col' => $tab === 'faq' || $described, 'items-center pt-1' => $tab !== 'faq' && ! $described])>
                                                    <button type="button" wire:click="moveItem({{ $index }}, -1)" @disabled($loop->first) class="rounded p-1 text-muted hover:text-ink disabled:opacity-30" aria-label="{{ __('Move up') }}"><i class="fa-solid fa-chevron-up text-xs" aria-hidden="true"></i></button>
                                                    <button type="button" wire:click="moveItem({{ $index }}, 1)" @disabled($loop->last) class="rounded p-1 text-muted hover:text-ink disabled:opacity-30" aria-label="{{ __('Move down') }}"><i class="fa-solid fa-chevron-down text-xs" aria-hidden="true"></i></button>
                                                    <button type="button" wire:click="removeItem({{ $index }})" class="rounded p-1 text-muted hover:text-red-600" aria-label="{{ __('Remove') }}"><i class="fa-regular fa-trash-can text-xs" aria-hidden="true"></i></button>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                                @if (count($form['items']) < $maxItems)
                                    <x-ui.button type="button" variant="secondary" size="sm" class="mt-3" icon="fa-solid fa-plus" wire:click="addItem">{{ $tab === 'faq' ? __('Add question') : __('Add item') }}</x-ui.button>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </x-ui.card>

            <div class="flex justify-end">
                <x-ui.button type="submit" icon="fa-regular fa-floppy-disk" wire:loading.attr="disabled" wire:target="save">{{ __('Save') }}</x-ui.button>
            </div>
        </form>
    @endif
</div>
