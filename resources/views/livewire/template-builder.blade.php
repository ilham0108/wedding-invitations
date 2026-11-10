<div x-data="{
        device: 'mobile',
        mobilePanel: 'preview',
        base: @js($previewUrl),
        {{-- Preview sections have the id section-preview-{position}. --}}
        selectedAnchor() {
            const index = this.$wire.sections.findIndex((section) => section.key === this.$wire.selected);

            return index >= 0 ? 'preview-' + index : null;
        },
        refresh() {
            window.previewFrame.reload(this.$refs.frame, this.base + '?content=' + this.$wire.content, this.selectedAnchor());
        },
        preview: null,
    }"
    {{-- Look-only changes are patched into the preview (resources/js/builder-preview.js); ornaments of the edited section can be dragged there. --}}
    x-init="preview = window.builderPreview.attach($refs.frame, $el, (index, x, y, width) => $wire.placeOrnament(index, x, y, width))"
    data-ornament-owner="{{ $ornamentOwner }}" data-ornament-list="{{ $ornamentList }}"
    {{-- Events come from TemplateBuilder ($wire.$watch does not fire for $set() changes in Livewire 4). --}}
    x-on:template-preview-patch.window="$event.detail.patch.group && $event.detail.patch.group !== $wire.content ? refresh() : preview.apply($event.detail.patch)"
    x-on:template-preview-refresh.window="refresh()"
    x-on:builder-section-selected.window="window.previewFrame.scrollTo($refs.frame, $event.detail.anchor)"
    x-on:template-draft-updated.window="$event.detail.patched || refresh()"
    class="flex h-[calc(100svh-4rem)] flex-col overflow-clip">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-line bg-white px-4 py-3">
        <a href="{{ route('admin.templates.index') }}" class="rounded-lg p-2 text-ink-soft hover:bg-sage-soft" aria-label="{{ __('Back to templates') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <div class="min-w-0">
            <p class="truncate font-medium text-ink">{{ $template->name }}</p>
            <p class="text-xs text-muted">
                <span wire:loading.remove>
                    @if ($hasChanges)
                        <i class="fa-solid fa-circle text-[8px] text-amber-500" aria-hidden="true"></i> {{ __('Unsaved changes') }}
                    @else
                        {{ __('All changes saved') }}
                    @endif
                </span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Updating preview…') }}</span>
            </p>
        </div>
        <x-ui.badge :variant="$template->isPublished() ? 'success' : 'neutral'">{{ $template->status->label() }}</x-ui.badge>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <div class="hidden rounded-lg border border-line p-0.5 lg:flex" role="group" aria-label="{{ __('Preview device') }}">
                @foreach (['mobile' => 'fa-solid fa-mobile-screen', 'tablet' => 'fa-solid fa-tablet-screen-button', 'desktop' => 'fa-solid fa-desktop'] as $mode => $icon)
                    <button type="button" x-on:click="device = '{{ $mode }}'" class="rounded-md px-2.5 py-1.5 text-sm"
                        x-bind:class="device === '{{ $mode }}' ? 'bg-ink text-white' : 'text-ink-soft hover:bg-paper'" aria-label="{{ ucfirst($mode) }}">
                        <i class="{{ $icon }}" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>

            @if ($hasChanges)
                <x-ui.button type="button" variant="ghost" size="sm" wire:click="discard" data-confirm="{{ __('Discard all unsaved changes?') }}">{{ __('Discard') }}</x-ui.button>
            @endif
            <x-ui.button type="button" size="sm" icon="fa-regular fa-floppy-disk" wire:click="save" wire:loading.attr="disabled" wire:target="save" :disabled="! $hasChanges"
                :data-confirm="$template->isPublished() ? __('This template is published. Save the changes for new invitations?') : null">{{ __('Save template') }}</x-ui.button>
        </div>
    </div>

    {{-- Each panel is positioned so absolute children (sr-only inputs) stay inside its scroll area; otherwise they stretch the page and the editor scrolls up, leaving a blank strip below. --}}
    <div class="relative grid min-h-0 flex-1 lg:grid-cols-[260px_minmax(0,1fr)_380px]">
        {{-- Left: section structure --}}
        <aside class="relative min-h-0 overflow-y-auto border-r border-line bg-paper p-3 lg:block"
            x-bind:class="{ 'max-lg:hidden': mobilePanel !== 'sections' }">
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
                            <button type="button" wire:click="addSection('{{ $type }}')" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-ink-soft hover:bg-paper">
                                <i class="{{ $registry->definition($type)['icon'] }} w-4 text-center text-muted" aria-hidden="true"></i>{{ __($label) }}
                            </button>
                        @endforeach
                    </div>
                </x-ui.dropdown>
            </div>

            <ul wire:sort="reorder" class="space-y-1">
                @foreach ($sections as $section)
                    <li wire:key="builder-section-{{ $section['key'] }}" wire:sort:item="{{ $section['key'] }}"
                        @class([
                            'group flex items-center gap-2 rounded-lg border px-2 py-2 text-sm transition',
                            'border-ink/20 bg-white shadow-soft' => $selected === $section['key'],
                            'border-transparent hover:bg-white' => $selected !== $section['key'],
                        ])>
                        <span wire:sort:handle class="cursor-grab px-1 text-muted active:cursor-grabbing" title="{{ __('Drag to reorder') }}">
                            <i class="fa-solid fa-grip-vertical" aria-hidden="true"></i>
                        </span>

                        <button type="button" wire:click="select('{{ $section['key'] }}')" class="min-w-0 flex-1 text-left">
                            <span class="block truncate font-medium text-ink">
                                <i class="{{ $registry->definition($section['type'])['icon'] }} mr-1 w-4 text-center text-muted" aria-hidden="true"></i>{{ __($registry->label($section['type'])) }}
                            </span>
                            <span class="block truncate pl-5 text-xs capitalize text-muted">
                                {{ __(str_replace('-', ' ', $section['variant'])) }}@if ($section['background'] !== 'default') · {{ __(ucfirst($section['background'])) }}@endif
                            </span>
                        </button>

                        <x-ui.dropdown width="w-44">
                            <x-slot:trigger>
                                <button type="button" class="rounded p-1 text-muted hover:text-ink" aria-label="{{ __('Section actions') }}">
                                    <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                </button>
                            </x-slot:trigger>
                            <button type="button" wire:click="duplicateSection('{{ $section['key'] }}')" class="block w-full px-4 py-2 text-left text-sm text-ink-soft hover:bg-paper">{{ __('Duplicate') }}</button>
                            <button type="button" wire:click="removeSection('{{ $section['key'] }}')" data-confirm="{{ __('Remove this section from the template?') }}"
                                class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">{{ __('Delete') }}</button>
                        </x-ui.dropdown>
                    </li>
                @endforeach
            </ul>
        </aside>

        {{-- Center: live preview of the draft --}}
        <div class="relative flex min-h-0 flex-col items-center gap-3 overflow-auto bg-[#e9e7e1] p-4 lg:p-6"
            x-bind:class="{ 'max-lg:hidden': mobilePanel !== 'preview' }">
            <div class="flex rounded-lg border border-line bg-white p-0.5 text-xs" role="group" aria-label="{{ __('Preview content') }}">
                @foreach (['default_configuration' => __('Default content'), 'sample_content' => __('Sample content')] as $group => $groupLabel)
                    <button type="button" wire:click="$set('content', '{{ $group }}')"
                        @class(['rounded-md px-3 py-1.5', 'bg-ink text-white' => $content === $group, 'text-ink-soft hover:bg-paper' => $content !== $group])>{{ $groupLabel }}</button>
                @endforeach
            </div>
            <div class="w-full flex-1 overflow-hidden rounded-2xl border border-line bg-white shadow-soft transition-all"
                x-bind:style="{ maxWidth: { mobile: '400px', tablet: '780px', desktop: '100%' }[device] }">
                <iframe x-ref="frame" src="{{ $previewUrl }}?content={{ $content }}" wire:ignore title="{{ __('Live preview') }}" class="h-full min-h-[60svh] w-full"></iframe>
            </div>
        </div>

        {{-- Right: properties --}}
        <aside class="min-h-0 overflow-y-auto border-l border-line bg-white lg:relative max-lg:fixed max-lg:inset-x-0 max-lg:bottom-14 max-lg:z-40 max-lg:max-h-[70svh] max-lg:rounded-t-2xl max-lg:shadow-2xl lg:block"
            x-data="{ open: {}, toggle(id) { this.open[id] = ! this.open[id] } }"
            x-bind:class="{ 'max-lg:hidden': mobilePanel !== 'edit' }">
            <div class="sticky top-0 z-10 flex border-b border-line bg-white" role="tablist">
                @foreach (['section' => __('Section'), 'theme' => __('Theme'), 'music' => __('Music')] as $key => $label)
                    <button type="button" role="tab" wire:click="$set('panel', '{{ $key }}')"
                        @class(['flex-1 border-b-2 px-3 py-3 text-sm font-medium', 'border-ink text-ink' => $panel === $key, 'border-transparent text-muted hover:text-ink' => $panel !== $key])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="p-4">
                @if ($panel === 'theme')
                    <div class="space-y-3">
                    <x-builder.group id="theme-design" :title="__('Design')" icon="fa-solid fa-swatchbook">
                        <div>
                            <div class="space-y-2">
                                @foreach ($designs as $key => $designOption)
                                    <button type="button" wire:click="setDesign('{{ $key }}')"
                                        @class(['block w-full rounded-lg border px-3 py-2.5 text-left', 'border-ink bg-paper' => $design === $key, 'border-line hover:border-ink/30' => $design !== $key])>
                                        <span class="flex items-center justify-between gap-2 text-sm font-medium text-ink">
                                            {{ __($designOption['label']) }}
                                            @if ($design === $key)<i class="fa-solid fa-check text-sage" aria-hidden="true"></i>@endif
                                        </span>
                                        <span class="mt-0.5 block text-xs text-muted">{{ __($designOption['description']) }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                        @if ($hasDesktopLayouts)
                            <div>
                                <p class="mb-2 text-sm font-medium text-ink">{{ __('Desktop layout') }}</p>
                                <p class="mb-2 text-xs text-muted">{{ __('How the invitation looks on laptops and wide screens. Phones always show it full screen.') }}</p>
                                <div class="space-y-2">
                                    @foreach ($desktopLayouts as $key => [$layoutLabel, $layoutDescription, $layoutIcon])
                                        <button type="button" wire:click="setDecoration('desktop_layout', '{{ $key }}')"
                                            @class(['flex w-full items-start gap-3 rounded-lg border px-3 py-2.5 text-left', 'border-ink bg-paper' => $decoration['desktop_layout'] === $key, 'border-line hover:border-ink/30' => $decoration['desktop_layout'] !== $key])>
                                            <i class="{{ $layoutIcon }} mt-0.5 w-4 text-ink-soft" aria-hidden="true"></i>
                                            <span>
                                                <span class="block text-sm font-medium text-ink">{{ __($layoutLabel) }}</span>
                                                <span class="mt-0.5 block text-xs text-muted">{{ __($layoutDescription) }}</span>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                                @if ($decoration['desktop_layout'] === 'split')
                                    <p class="mt-2 text-xs text-muted">{{ __('Set the panel text in the Cover section and its ornaments under "Desktop side panel".') }}</p>
                                @endif
                            </div>

                            <div>
                                <p class="mb-2 text-sm font-medium text-ink">{{ __('Texture') }}</p>
                                <p class="mb-2 text-xs text-muted">{{ __('A faint pattern on the page background; photos are not covered.') }}</p>
                                <div class="flex rounded-lg border border-line p-0.5">
                                    @foreach ($textures as $key => $textureLabel)
                                        <button type="button" wire:click="setDecoration('texture', '{{ $key }}')"
                                            @class(['flex-1 rounded-md px-2 py-1.5 text-xs', 'bg-ink text-white' => $decoration['texture'] === $key, 'text-ink-soft hover:bg-paper' => $decoration['texture'] !== $key])>{{ __($textureLabel) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </x-builder.group>
                    <x-builder.group id="theme-colors" :title="__('Colors & fonts')" icon="fa-solid fa-palette">
                        @include('livewire.partials.theme-panel')
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink">{{ __('Font sizes') }}</p>
                            <p class="mb-2 text-xs text-muted">{{ __('For every section; a section can still make its own headings or text smaller or larger.') }}</p>
                            @foreach (['heading_scale' => __('Headings'), 'text_scale' => __('Text')] as $scaleKey => $scaleLabel)
                                <p class="mb-1.5 mt-3 text-xs font-medium text-ink">{{ $scaleLabel }}</p>
                                <div class="flex rounded-lg border border-line p-0.5">
                                    @foreach ($fontScales as $key => $fontLabel)
                                        <button type="button" wire:click="setDecoration('{{ $scaleKey }}', '{{ $key }}')"
                                            @class(['flex-1 rounded-md px-2 py-1.5 text-xs', 'bg-ink text-white' => $decoration[$scaleKey] === $key, 'text-ink-soft hover:bg-paper' => $decoration[$scaleKey] !== $key])>{{ __($fontLabel) }}</button>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </x-builder.group>
                    <x-builder.group id="theme-decoration" :title="__('Decoration')" icon="fa-solid fa-seedling">
                        {{-- Divider: classic styles, or a library ornament that every design uses for its divider marks. --}}
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink">{{ __('Divider') }}</p>
                            <p class="mb-2 text-xs text-muted">{{ __('The separating mark on the cover, between events and in the closing. An ornament image keeps its own colors.') }}</p>
                            <div @class(['grid gap-2', 'grid-cols-5' => count($dividers) > 2, 'grid-cols-3' => count($dividers) <= 2])>
                                @foreach ($dividers as $key => [$dividerLabel, $dividerSample])
                                    <button type="button" wire:click="setDecoration('divider', '{{ $key }}')"
                                        @class(['flex flex-col items-center justify-center gap-1 rounded-lg border px-1 py-2.5 text-xs', 'border-ink bg-paper font-medium text-ink' => $decoration['divider'] === $key, 'border-line text-ink-soft hover:border-ink/30' => $decoration['divider'] !== $key])>
                                        <span class="font-semibold tracking-tight text-accent" aria-hidden="true">{{ $dividerSample }}</span>{{ __($dividerLabel) }}
                                    </button>
                                @endforeach
                                <button type="button" wire:click="$toggle('dividerPicker')" aria-expanded="{{ $dividerPicker ? 'true' : 'false' }}"
                                    @class(['flex flex-col items-center justify-center gap-1 rounded-lg border px-1 py-2 text-xs', 'border-ink bg-paper font-medium text-ink' => $decoration['divider'] === 'image', 'border-line text-ink-soft hover:border-ink/30' => $decoration['divider'] !== 'image'])>
                                    @if ($dividerImage)
                                        <img src="{{ $dividerImage }}" alt="" class="h-4 w-16 object-contain">
                                    @else
                                        <i class="fa-regular fa-image" aria-hidden="true"></i>
                                    @endif
                                    {{ __('Own image') }}
                                </button>
                            </div>

                            @if ($dividerPicker)
                                <div class="mt-3 rounded-lg border border-line p-2">
                                    @if ($dividerIllustrations->isEmpty())
                                        <p class="p-2 text-xs text-muted">{{ __('No ornaments in the library yet. Upload one under Illustrations → Ornament.') }}</p>
                                    @else
                                        <div class="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto">
                                            @foreach ($dividerIllustrations as $illustration)
                                                <button type="button" wire:key="divider-ornament-{{ $illustration->ulid }}" wire:click="chooseDividerImage('{{ $illustration->ulid }}')"
                                                    class="flex aspect-square items-center justify-center rounded-md border border-line bg-paper p-1 hover:border-ink/30" title="{{ $illustration->name }}">
                                                    <img src="{{ $illustration->url() }}" alt="{{ $illustration->name }}" loading="lazy" class="max-h-full max-w-full object-contain">
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @if ($decoration['divider'] === 'image')
                                <p class="mb-2 mt-4 text-xs font-medium text-ink">{{ __('Size') }}</p>
                                <div class="flex rounded-lg border border-line p-0.5">
                                    @foreach ($headingOrnamentSizes as $key => $sizeLabel)
                                        <button type="button" wire:click="setDecoration('divider_size', '{{ $key }}')"
                                            @class(['flex-1 rounded-md px-2 py-1.5 text-xs', 'bg-ink text-white' => $decoration['divider_size'] === $key, 'text-ink-soft hover:bg-paper' => $decoration['divider_size'] !== $key])>{{ __($sizeLabel) }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink">{{ __('Ornament under headings') }}</p>
                            <p class="mb-2 text-xs text-muted">{{ __('Shown under every section heading. Built-in icons take the secondary color; your own image keeps its colors.') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($headingOrnaments as $key => $ornamentLabel)
                                    <button type="button" wire:click="setDecoration('heading_ornament', '{{ $key }}')"
                                        @class(['flex flex-col items-center justify-center gap-1 rounded-lg border px-1 py-2 text-xs', 'border-ink bg-paper font-medium text-ink' => $decoration['heading_ornament'] === $key, 'border-line text-ink-soft hover:border-ink/30' => $decoration['heading_ornament'] !== $key])>
                                        @if (! in_array($key, ['design', 'none'], true))
                                            <img src="{{ asset('images/heading-ornaments/'.$key.'.svg') }}" alt="" class="h-4 w-16 object-contain opacity-70">
                                        @endif
                                        {{ __($ornamentLabel) }}
                                    </button>
                                @endforeach
                                <button type="button" wire:click="$toggle('headingOrnamentPicker')"
                                    @class(['flex flex-col items-center justify-center gap-1 rounded-lg border px-1 py-2 text-xs', 'border-ink bg-paper font-medium text-ink' => $decoration['heading_ornament'] === 'image', 'border-line text-ink-soft hover:border-ink/30' => $decoration['heading_ornament'] !== 'image'])>
                                    @if ($headingOrnamentImage)
                                        <img src="{{ $headingOrnamentImage }}" alt="" class="h-4 w-16 object-contain">
                                    @else
                                        <i class="fa-regular fa-image" aria-hidden="true"></i>
                                    @endif
                                    {{ __('Own image') }}
                                </button>
                            </div>

                            @if ($headingOrnamentPicker)
                                <div class="mt-3 rounded-lg border border-line p-2">
                                    @if ($headingOrnamentIllustrations->isEmpty())
                                        <p class="p-2 text-xs text-muted">{{ __('No ornaments in the library yet. Upload one under Illustrations → Ornament.') }}</p>
                                    @else
                                        <div class="grid max-h-56 grid-cols-3 gap-2 overflow-y-auto">
                                            @foreach ($headingOrnamentIllustrations as $illustration)
                                                <button type="button" wire:key="heading-ornament-{{ $illustration->ulid }}" wire:click="chooseHeadingOrnamentImage('{{ $illustration->ulid }}')"
                                                    class="flex aspect-square items-center justify-center rounded-md border border-line bg-paper p-1 hover:border-ink/30" title="{{ $illustration->name }}">
                                                    <img src="{{ $illustration->url() }}" alt="{{ $illustration->name }}" loading="lazy" class="max-h-full max-w-full object-contain">
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif

                            @unless (in_array($decoration['heading_ornament'], ['design', 'none'], true))
                                <p class="mb-2 mt-4 text-xs font-medium text-ink">{{ __('Size') }}</p>
                                <div class="flex rounded-lg border border-line p-0.5">
                                    @foreach ($headingOrnamentSizes as $key => $sizeLabel)
                                        <button type="button" wire:click="setDecoration('heading_ornament_size', '{{ $key }}')"
                                            @class(['flex-1 rounded-md px-2 py-1.5 text-xs', 'bg-ink text-white' => $decoration['heading_ornament_size'] === $key, 'text-ink-soft hover:bg-paper' => $decoration['heading_ornament_size'] !== $key])>{{ __($sizeLabel) }}</button>
                                    @endforeach
                                </div>
                            @endunless
                        </div>
                    </x-builder.group>
                    <x-builder.group id="theme-motion" :title="__('Motion & navigation')" icon="fa-solid fa-arrows-up-down-left-right">
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink">{{ __('Animation') }}</p>
                            <p class="mb-2 text-xs text-muted">{{ __('How sections appear while guests scroll. Visitors who turned off motion on their device see no animation.') }}</p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($animations as $key => [$animationLabel, $animationIcon])
                                    <button type="button" wire:click="setDecoration('animation', '{{ $key }}')"
                                        @class(['flex flex-col items-center gap-1 rounded-lg border px-2 py-2.5 text-xs', 'border-ink bg-paper font-medium text-ink' => $decoration['animation'] === $key, 'border-line text-ink-soft hover:border-ink/30' => $decoration['animation'] !== $key])>
                                        <i class="{{ $animationIcon }}" aria-hidden="true"></i>{{ __($animationLabel) }}
                                    </button>
                                @endforeach
                            </div>
                            @if ($decoration['animation'] !== 'none')
                                <p class="mb-2 mt-4 text-xs font-medium text-ink">{{ __('Speed') }}</p>
                                <div class="flex rounded-lg border border-line p-0.5">
                                    @foreach ($animationSpeeds as $key => $speedLabel)
                                        <button type="button" wire:click="setDecoration('animation_speed', '{{ $key }}')"
                                            @class(['flex-1 rounded-md px-2 py-1.5 text-xs', 'bg-ink text-white' => $decoration['animation_speed'] === $key, 'text-ink-soft hover:bg-paper' => $decoration['animation_speed'] !== $key])>{{ __($speedLabel) }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <div>
                            <p class="mb-2 text-sm font-medium text-ink">{{ __('How guests move between sections') }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach ($scrollDirections as $key => [$directionLabel, $directionIcon])
                                    <button type="button" wire:click="setDecoration('scroll_direction', '{{ $key }}')"
                                        @class(['flex min-h-11 items-center justify-center gap-2 rounded-lg border px-2 py-2.5 text-xs', 'border-ink bg-paper font-medium text-ink' => $decoration['scroll_direction'] === $key, 'border-line text-ink-soft hover:border-ink/30' => $decoration['scroll_direction'] !== $key])>
                                        <i class="{{ $directionIcon }}" aria-hidden="true"></i>{{ __($directionLabel) }}
                                    </button>
                                @endforeach
                            </div>
                            @if ($decoration['scroll_direction'] === 'horizontal')
                                <p class="mt-2 text-xs text-muted">{{ __('Each section becomes a page. A section longer than the screen scrolls down inside its page.') }}</p>
                            @endif
                        </div>
                    </x-builder.group>
                    </div>
                @elseif ($panel === 'music')
                    @include('livewire.partials.template-music-panel')
                @elseif ($selectedSection)
                    @php
                        $sectionKey = $selectedSection['key'];
                        $sectionLabel = __($registry->label($selectedSection['type']));
                        $variants = $registry->variants($selectedSection['type']);
                        // One icon per layout, so the layouts can be told apart at a glance.
                        $variantIcons = [
                            'centered' => 'fa-solid fa-align-center', 'framed' => 'fa-regular fa-square', 'side-by-side' => 'fa-solid fa-table-columns',
                            'stacked' => 'fa-solid fa-grip-lines', 'photo-beside' => 'fa-regular fa-address-card', 'cards' => 'fa-regular fa-clone',
                            'list' => 'fa-solid fa-list', 'grid' => 'fa-solid fa-table-cells', 'masonry' => 'fa-solid fa-table-cells-large', 'slideshow' => 'fa-regular fa-images',
                        ];
                        $selectClass = 'block min-h-11 w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage';
                        $segmentClass = 'flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-lg border px-2 py-2 text-sm transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage';
                        $segmentOn = 'border-sage bg-sage font-medium text-white';
                        $segmentOff = 'border-line bg-white text-ink-soft hover:border-ink/30 hover:text-ink active:bg-paper';
                    @endphp

                    <div class="space-y-3">
                        {{-- Which section is being edited. --}}
                        <div class="flex items-start gap-3 px-0.5 pb-1">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-line bg-paper text-ink-soft">
                                <i class="{{ $registry->definition($selectedSection['type'])['icon'] ?? 'fa-solid fa-layer-group' }}" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0">
                                <h2 class="font-display text-xl leading-tight text-ink">{{ $sectionLabel }}</h2>
                                <p class="mt-0.5 text-xs text-muted">{{ __('Adjust the look and typography of the :section section.', ['section' => \Illuminate\Support\Str::lower($sectionLabel)]) }}</p>
                            </div>
                        </div>

                        <x-builder.group id="section-layout" :title="__('Layout & background')" icon="fa-solid fa-table-cells-large">
                            @if (count($variants) > 1)
                                <div role="group" aria-labelledby="section-layout-label">
                                    <p id="section-layout-label" class="mb-2 text-sm font-medium text-ink">{{ __('Layout') }}</p>
                                    {{-- Three layouts share the row, so their icon goes above the name and long names can wrap. --}}
                                    @php($stacked = count($variants) >= 3)
                                    <div @class(['grid gap-2', 'grid-cols-2' => ! $stacked, 'grid-cols-3' => $stacked])>
                                        @foreach ($variants as $option)
                                            <button type="button" wire:click="setVariant('{{ $sectionKey }}', '{{ $option }}')" aria-pressed="{{ $selectedSection['variant'] === $option ? 'true' : 'false' }}"
                                                @class([$segmentClass, 'min-w-0', 'flex-col gap-1 text-xs leading-tight' => $stacked, $segmentOn => $selectedSection['variant'] === $option, $segmentOff => $selectedSection['variant'] !== $option])>
                                                @isset($variantIcons[$option])<i class="{{ $variantIcons[$option] }} text-xs" aria-hidden="true"></i>@endisset
                                                <span class="min-w-0 text-center [overflow-wrap:anywhere] first-letter:uppercase">{{ __(str_replace('-', ' ', $option)) }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($selectedSection['type'] !== 'cover')
                                <div role="group" aria-labelledby="section-background-label">
                                    <p id="section-background-label" class="mb-2 text-sm font-medium text-ink">{{ __('Background') }}</p>
                                    <div @class(['grid gap-2', 'grid-cols-2' => count($backgrounds) % 2 === 0, 'grid-cols-3' => count($backgrounds) % 2 === 1])>
                                        @foreach ($backgrounds as $option => $backgroundLabel)
                                            <button type="button" wire:click="setBackground('{{ $sectionKey }}', '{{ $option }}')" aria-pressed="{{ $selectedSection['background'] === $option ? 'true' : 'false' }}"
                                                @class([$segmentClass, 'text-xs', $segmentOn => $selectedSection['background'] === $option, $segmentOff => $selectedSection['background'] !== $option])>{{ __($backgroundLabel) }}</button>
                                        @endforeach
                                    </div>
                                    <p class="mt-1.5 text-xs text-muted">{{ __('Continue previous keeps the last background photo in view. Plain uses the theme background color.') }}</p>
                                </div>

                                {{-- The cover is always screen height; its title is placed with the cover's own position fields. --}}
                                <div>
                                    <label for="section-height" class="mb-2 block text-sm font-medium text-ink">{{ __('Section height') }}</label>
                                    <select id="section-height" x-on:change="$wire.setHeight('{{ $sectionKey }}', $event.target.value)" class="{{ $selectClass }}">
                                        @foreach ($heights as $option => $label)
                                            <option value="{{ $option }}" @selected(($selectedSection['height'] ?? 'auto') === $option)>{{ __($label) }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1.5 text-xs text-muted">{{ __('Screen height makes the section at least as tall as the guest\'s screen, e.g. for the closing or a quote.') }}</p>
                                </div>
                            @endif
                        </x-builder.group>

                        {{-- Fonts, sizes and weights of headings, then of the running text. Weights: only those the font loads, so the browser never fakes a bold. --}}
                        @php($weightNames = app(\App\Services\FontRegistry::class)->weightNames())
                        <x-builder.group id="section-typography" :title="__('Typography')" icon="fa-solid fa-font">
                            @foreach (['font_heading' => [__('Heading font'), 'heading_size', __('Heading size'), 'heading_weight', __('Heading weight')], 'font_body' => [__('Body font'), 'text_size', __('Text size'), 'body_weight', __('Text weight')]] as $fontKey => [$fontLabel, $sizeKey, $sizeLabel, $weightKey, $weightLabel])
                                <div class="space-y-3">
                                    <div>
                                        <label for="section-{{ $fontKey }}" class="mb-2 block text-sm font-medium text-ink">{{ $fontLabel }}</label>
                                        <select id="section-{{ $fontKey }}" x-on:change="$wire.setSectionFont('{{ $sectionKey }}', '{{ $fontKey }}', $event.target.value)" class="{{ $selectClass }}">
                                            <option value="default">{{ __('Follow the theme (:font)', ['font' => $theme[$fontKey] ?? '']) }}</option>
                                            @foreach ($fontsByCategory as $category => $fonts)
                                                <optgroup label="{{ __(ucfirst($category)) }}">
                                                    @foreach ($fonts as $font)
                                                        <option value="{{ $font['name'] }}" @selected(($selectedSection['fonts'][$fontKey] ?? null) === $font['name'])>{{ $font['name'] }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <label for="section-{{ $sizeKey }}" class="mb-1.5 block text-xs font-medium text-ink-soft">{{ $sizeLabel }}</label>
                                            <select id="section-{{ $sizeKey }}" x-on:change="$wire.setFontSize('{{ $sectionKey }}', '{{ $sizeKey }}', $event.target.value)" class="{{ $selectClass }}">
                                                @foreach ($sectionFontSizes as $option => $optionLabel)
                                                    <option value="{{ $option }}" @selected(($selectedSection[$sizeKey] ?? 'default') === $option)>{{ __($optionLabel) }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label for="section-{{ $weightKey }}" class="mb-1.5 block text-xs font-medium text-ink-soft">{{ $weightLabel }}</label>
                                            <select id="section-{{ $weightKey }}" x-on:change="$wire.setSectionFont('{{ $sectionKey }}', '{{ $weightKey }}', $event.target.value)" class="{{ $selectClass }}">
                                                <option value="default">{{ __('Follow the theme') }}</option>
                                                @foreach ($sectionFontWeights[$weightKey] as $weight)
                                                    <option value="{{ $weight }}" @selected(($selectedSection['fonts'][$weightKey] ?? null) === $weight)>{{ $weight }} · {{ $weightNames[$weight] ?? $weight }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    {{-- The cover has its own alignment field; a quote has no title. --}}
                                    @if ($fontKey === 'font_heading' && ! in_array($selectedSection['type'], ['cover', 'quote'], true))
                                        <div>
                                            <label for="section-heading-align" class="mb-1.5 block text-xs font-medium text-ink-soft">{{ __('Heading alignment') }}</label>
                                            <select id="section-heading-align" x-on:change="$wire.setHeadingAlign('{{ $sectionKey }}', $event.target.value)" class="{{ $selectClass }}">
                                                @foreach (['default' => __('Design default'), 'left' => __('Left'), 'center' => __('Center'), 'right' => __('Right')] as $option => $optionLabel)
                                                    <option value="{{ $option }}" @selected(($selectedSection['heading_align'] ?? 'default') === $option)>{{ $optionLabel }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endif
                                </div>
                                @if ($loop->first)
                                    <hr class="border-line">
                                @endif
                            @endforeach
                        </x-builder.group>

                        {{-- The section's own colors; the contrast is measured against the section's background color. --}}
                        <x-builder.group id="section-colors" :title="__('Colors')" icon="fa-solid fa-palette">
                            @foreach (['heading' => __('Heading color'), 'text' => __('Text color'), ...($selectedSection['type'] === 'couple' ? ['social_icon' => __('Social icon color'), 'social_bg' => __('Social icon background')] : [])] as $colorKey => $colorLabel)
                                @php($color = $selectedSection['colors'][$colorKey] ?? null)
                                @php($followsDesign = in_array($colorKey, ['social_icon', 'social_bg'], true))
                                <div>
                                    <label for="section-color-{{ $colorKey }}" class="mb-1.5 block text-sm text-ink">{{ $colorLabel }}</label>
                                    <div class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2">
                                        <input type="color" wire:key="section-swatch-{{ $colorKey }}-{{ $color ?? 'theme' }}" id="section-color-{{ $colorKey }}" value="{{ $color ?? $theme['text_color'] }}"
                                            x-on:change="$wire.setSectionColor('{{ $sectionKey }}', '{{ $colorKey }}', $event.target.value)"
                                            class="h-11 w-11 shrink-0 cursor-pointer rounded-lg border border-line bg-white p-1">
                                        {{-- Keyed by value so a picker change re-renders it. Empty goes back to the theme; an invalid code is undone. --}}
                                        <input type="text" wire:key="section-hex-{{ $colorKey }}-{{ $color ?? 'theme' }}" value="{{ $color }}" placeholder="{{ $followsDesign ? __('Design default') : __('Follow the theme') }}" maxlength="7" spellcheck="false" aria-label="{{ $colorLabel }}: {{ __('Hex code') }}"
                                            x-on:keydown.enter.prevent="$el.blur()"
                                            x-on:change="
                                                let hex = '#' + $el.value.trim().replace(/^#/, '').toLowerCase();
                                                if (hex === '#') { $wire.setSectionColor('{{ $sectionKey }}', '{{ $colorKey }}', 'default'); return }
                                                if (/^#[0-9a-f]{3}$/.test(hex)) hex = '#' + [...hex.slice(1)].map(c => c + c).join('');
                                                if (/^#[0-9a-f]{6}$/.test(hex)) { $el.value = hex; $wire.setSectionColor('{{ $sectionKey }}', '{{ $colorKey }}', hex) } else { $el.value = @js($color ?? '') }
                                            "
                                            class="h-11 min-w-0 rounded-lg border-line px-2.5 font-mono text-xs text-ink-soft placeholder:font-sans placeholder:text-muted focus:border-sage focus:ring-sage">
                                        @if ($color)
                                            <button type="button" wire:click="setSectionColor('{{ $sectionKey }}', '{{ $colorKey }}', 'default')"
                                                class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-lg text-ink-soft hover:bg-paper hover:text-ink active:bg-line"
                                                aria-label="{{ $colorLabel }}: {{ $followsDesign ? __('Use the design default') : __('Use the theme color') }}" title="{{ $followsDesign ? __('Use the design default') : __('Use the theme color') }}">
                                                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>
                                            </button>
                                        @else
                                            <span class="w-11" aria-hidden="true"></span>
                                        @endif
                                    </div>
                                    @isset($sectionColorContrast[$colorKey])
                                        @php($contrast = $sectionColorContrast[$colorKey])
                                        @if ($contrast['ratio'] < $contrast['minimum'])
                                            <p class="mt-1.5 text-xs text-red-700">
                                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                                {{ __('Contrast :ratio:1, below :minimum:1: hard to read for some guests.', ['ratio' => \Illuminate\Support\Number::format($contrast['ratio'], 1), 'minimum' => \Illuminate\Support\Number::format($contrast['minimum'], 1)]) }}
                                            </p>
                                        @else
                                            <p class="mt-1.5 text-xs text-muted">{{ __($followsDesign ? 'Contrast :ratio:1 against the icon background.' : 'Contrast :ratio:1 against the section background.', ['ratio' => \Illuminate\Support\Number::format($contrast['ratio'], 1)]) }}</p>
                                        @endif
                                    @endisset
                                </div>
                            @endforeach
                            <p class="text-xs text-muted">{{ __('With a background photo, check the colors in the preview: the contrast is measured against the section color only.') }}</p>
                        </x-builder.group>

                        {{-- Entrance of single elements (SectionAnimations). Changing one makes the whole section enter element by element. --}}
                        @php($sectionAnimations = $selectedSection['animations'] ?? [])
                        <x-builder.group id="section-animation" :title="__('Animation')" icon="fa-solid fa-wand-magic-sparkles" :count="count($sectionAnimations) ?: null">
                            <p class="text-xs text-muted">{{ __('Change one element and this section enters element by element while guests scroll. Elements on "Follow the theme" use the theme animation.') }}</p>
                            @php($animationService = app(\App\Services\Invitation\SectionAnimations::class))
                            @foreach ($animationService->targets($selectedSection['type']) as $target => $targetLabel)
                                @php($animation = $sectionAnimations[$target] ?? ['effect' => 'default', 'delay' => 0])
                                <div wire:key="section-animation-{{ $sectionKey }}-{{ $target }}-{{ $animation['effect'] }}-{{ $animation['delay'] }}-{{ $animation['pattern'] ?? 'same' }}" class="rounded-lg border border-line p-3">
                                    <label for="section-animation-{{ $target }}" class="mb-1.5 block text-sm font-medium text-ink">{{ __($targetLabel) }}</label>
                                    <select id="section-animation-{{ $target }}" x-on:change="$wire.setAnimation('{{ $sectionKey }}', '{{ $target }}', 'effect', $event.target.value)" class="{{ $selectClass }}">
                                        @foreach (['default' => 'Follow the theme', 'none' => 'No entrance', 'fade' => 'Fade', 'fade-up' => 'Fade up', 'fade-down' => 'Fade down', 'fade-right' => 'Fade from the left', 'fade-left' => 'Fade from the right', 'zoom-in' => 'Zoom in', 'zoom-out' => 'Zoom out'] as $option => $optionLabel)
                                            <option value="{{ $option }}" @selected($animation['effect'] === $option)>{{ __($optionLabel) }}</option>
                                        @endforeach
                                    </select>
                                    {{-- A list can bring its items in from alternating sides, however many items the client adds. --}}
                                    @if ($animationService->isList($selectedSection['type'], $target))
                                        <div role="group" aria-labelledby="section-animation-{{ $target }}-pattern" class="mt-3">
                                            <p id="section-animation-{{ $target }}-pattern" class="mb-1.5 text-xs font-medium text-ink-soft">{{ __('Item order') }}</p>
                                            <div class="grid grid-cols-2 gap-2">
                                                @foreach (['same' => [__('All the same'), 'fa-solid fa-grip-lines'], 'zigzag' => [__('Zigzag'), 'fa-solid fa-arrow-right-arrow-left']] as $pattern => [$patternLabel, $patternIcon])
                                                    @php($on = ($animation['pattern'] ?? 'same') === $pattern)
                                                    <button type="button" wire:click="setAnimation('{{ $sectionKey }}', '{{ $target }}', 'pattern', '{{ $pattern }}')" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                                        @class([$segmentClass, 'text-xs', $segmentOn => $on, $segmentOff => ! $on])>
                                                        <i class="{{ $patternIcon }}" aria-hidden="true"></i>{{ $patternLabel }}
                                                    </button>
                                                @endforeach
                                            </div>
                                            @if (($animation['pattern'] ?? 'same') === 'zigzag')
                                                <p class="mt-1.5 text-xs text-muted">{{ __('Items come in from the left and the right in turn. With a zoom, they zoom in from the side.') }}</p>
                                            @endif
                                        </div>
                                    @endif
                                    <div x-data="{ delay: {{ $animation['delay'] }} }" class="mt-3">
                                        <div class="mb-1 flex items-center justify-between text-xs">
                                            <label for="section-animation-{{ $target }}-delay" class="font-medium text-ink-soft">{{ __('Delay') }}</label>
                                            <output for="section-animation-{{ $target }}-delay" class="tabular-nums text-ink-soft" x-text="@js(__(':value s')).replace(':value', (delay / 10).toLocaleString(@js(str_replace('_', '-', app()->getLocale())), { minimumFractionDigits: 1 }))">{{ __(':value s', ['value' => \Illuminate\Support\Number::format($animation['delay'] / 10, 1)]) }}</output>
                                        </div>
                                        <input type="range" id="section-animation-{{ $target }}-delay" min="0" max="{{ \App\Services\Invitation\SectionAnimations::MAX_DELAY }}" step="1" value="{{ $animation['delay'] }}"
                                            x-model.number="delay" x-on:change="$wire.setAnimation('{{ $sectionKey }}', '{{ $target }}', 'delay', String(delay))"
                                            class="block h-11 w-full cursor-pointer accent-sage">
                                    </div>
                                </div>
                            @endforeach
                            @if ($sectionAnimations)
                                <button type="button" wire:click="resetAnimations('{{ $sectionKey }}')"
                                    class="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-lg border border-line px-3 text-sm text-ink-soft hover:border-ink/30 hover:text-ink active:bg-paper focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sage">
                                    <i class="fa-solid fa-rotate-left" aria-hidden="true"></i>{{ __('Back to the design animation') }}
                                </button>
                            @endif
                        </x-builder.group>

                        <x-builder.group id="section-ornaments" :title="__('Ornaments')" icon="fa-solid fa-leaf" :count="count($selectedSection[$ornamentList] ?? [])">
                            @include('livewire.partials.ornament-panel')
                        </x-builder.group>

                        <x-builder.group id="section-content" :title="__('Content')" icon="fa-regular fa-pen-to-square">
                            <div class="flex rounded-lg border border-line p-0.5" role="tablist">
                                @foreach (['default_configuration' => __('Default content'), 'sample_content' => __('Sample content')] as $group => $groupLabel)
                                    <button type="button" role="tab" aria-selected="{{ $content === $group ? 'true' : 'false' }}" wire:click="$set('content', '{{ $group }}')"
                                        @class(['min-h-11 flex-1 cursor-pointer rounded-md px-2 py-1.5 text-xs font-medium focus-visible:outline focus-visible:outline-2 focus-visible:outline-sage', 'bg-sage text-white' => $content === $group, 'text-ink-soft hover:bg-paper' => $content !== $group])>{{ $groupLabel }}</button>
                                @endforeach
                            </div>

                            @if ($sharedCount > 1)
                                <p class="rounded-lg bg-paper p-3 text-xs text-ink-soft">{{ __('This template has :count :section sections. They share the same content.', ['count' => $sharedCount, 'section' => $sectionLabel]) }}</p>
                            @endif

                            <livewire:template-section-content :template-ulid="$templateUlid" :type="$selectedSection['type']" :group="$content" :variant="$selectedSection['variant']"
                                :key="'content-'.$selectedSection['type'].'-'.$content.'-'.$selectedSection['variant']" />
                        </x-builder.group>
                    </div>
                @else
                    <x-ui.empty-state icon="fa-solid fa-layer-group" :title="__('No section selected')" :description="__('Add or pick a section from the list.')" />
                @endif
            </div>
        </aside>

        {{-- Mobile bottom navigation --}}
        <nav class="fixed inset-x-0 bottom-0 z-50 grid h-14 grid-cols-3 border-t border-line bg-white lg:hidden" aria-label="{{ __('Builder panels') }}">
            @foreach (['sections' => ['fa-solid fa-layer-group', __('Sections')], 'preview' => ['fa-regular fa-eye', __('Preview')], 'edit' => ['fa-solid fa-sliders', __('Edit')]] as $key => [$icon, $label])
                <button type="button" x-on:click="mobilePanel = '{{ $key }}'" class="flex flex-col items-center justify-center gap-0.5 text-xs"
                    x-bind:class="mobilePanel === '{{ $key }}' ? 'text-ink font-medium' : 'text-muted'">
                    <i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}
                </button>
            @endforeach
        </nav>
    </div>
</div>
