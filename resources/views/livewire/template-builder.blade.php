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
    }"
    {{-- Events come from TemplateBuilder ($wire.$watch does not fire for $set() changes in Livewire 4). --}}
    x-on:template-preview-refresh.window="refresh()"
    x-on:builder-section-selected.window="window.previewFrame.scrollTo($refs.frame, $event.detail.anchor)"
    x-on:template-draft-updated.window="refresh()"
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
            x-bind:class="{ 'max-lg:hidden': mobilePanel !== 'edit' }">
            <div class="sticky top-0 z-10 flex border-b border-line bg-white" role="tablist">
                @foreach (['section' => __('Section'), 'theme' => __('Theme'), 'music' => __('Music')] as $key => $label)
                    <button type="button" role="tab" wire:click="$set('panel', '{{ $key }}')"
                        @class(['flex-1 border-b-2 px-3 py-3 text-sm font-medium', 'border-ink text-ink' => $panel === $key, 'border-transparent text-muted hover:text-ink' => $panel !== $key])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="p-4">
                @if ($panel === 'theme')
                    <div class="mb-6">
                        <p class="mb-2 text-sm font-medium text-ink">{{ __('Design') }}</p>
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
                        <div class="mb-6">
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

                        <div class="mb-6">
                            <p class="mb-2 text-sm font-medium text-ink">{{ __('Divider') }}</p>
                            <p class="mb-2 text-xs text-muted">{{ __('Shown under section headings and on the cover, in the accent color.') }}</p>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach ($dividers as $key => [$dividerLabel, $dividerSample])
                                    <button type="button" wire:click="setDecoration('divider', '{{ $key }}')"
                                        @class(['flex flex-col items-center gap-1 rounded-lg border px-1 py-2.5 text-xs', 'border-ink bg-paper font-medium text-ink' => $decoration['divider'] === $key, 'border-line text-ink-soft hover:border-ink/30' => $decoration['divider'] !== $key])>
                                        <span class="font-semibold tracking-tight text-accent" aria-hidden="true">{{ $dividerSample }}</span>{{ __($dividerLabel) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-6">
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
                    <div class="mb-6">
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
                    @include('livewire.partials.theme-panel')
                @elseif ($panel === 'music')
                    @include('livewire.partials.template-music-panel')
                @elseif ($selectedSection)
                    <div class="space-y-5">
                        <h2 class="font-display text-xl text-ink">{{ __($registry->label($selectedSection['type'])) }}</h2>

                        @if (count($registry->variants($selectedSection['type'])) > 1)
                            <div>
                                <p class="mb-2 text-sm font-medium text-ink">{{ __('Layout') }}</p>
                                <div class="flex rounded-lg border border-line p-0.5">
                                    @foreach ($registry->variants($selectedSection['type']) as $option)
                                        <button type="button" wire:click="setVariant('{{ $selectedSection['key'] }}', '{{ $option }}')"
                                            @class(['flex-1 rounded-md px-2 py-1.5 text-xs capitalize', 'bg-ink text-white' => $selectedSection['variant'] === $option, 'text-ink-soft hover:bg-paper' => $selectedSection['variant'] !== $option])>{{ __(str_replace('-', ' ', $option)) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($selectedSection['type'] !== 'cover')
                            <div>
                                <p class="mb-2 text-sm font-medium text-ink">{{ __('Background') }}</p>
                                <div class="flex rounded-lg border border-line p-0.5">
                                    @foreach ($backgrounds as $option)
                                        <button type="button" wire:click="setBackground('{{ $selectedSection['key'] }}', '{{ $option }}')"
                                            @class(['flex-1 rounded-md px-2 py-1.5 text-xs', 'bg-ink text-white' => $selectedSection['background'] === $option, 'text-ink-soft hover:bg-paper' => $selectedSection['background'] !== $option])>{{ __(ucfirst($option)) }}</button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @include('livewire.partials.ornament-panel')

                        <div class="border-t border-line pt-5">
                            <div class="mb-4 flex rounded-lg border border-line p-0.5" role="tablist">
                                @foreach (['default_configuration' => __('Default content'), 'sample_content' => __('Sample content')] as $group => $groupLabel)
                                    <button type="button" role="tab" wire:click="$set('content', '{{ $group }}')"
                                        @class(['flex-1 rounded-md px-2 py-1.5 text-xs font-medium', 'bg-ink text-white' => $content === $group, 'text-ink-soft hover:bg-paper' => $content !== $group])>{{ $groupLabel }}</button>
                                @endforeach
                            </div>

                            @if ($sharedCount > 1)
                                <p class="mb-4 rounded-lg bg-paper p-3 text-xs text-ink-soft">{{ __('This template has :count :section sections. They share the same content.', ['count' => $sharedCount, 'section' => __($registry->label($selectedSection['type']))]) }}</p>
                            @endif

                            <livewire:template-section-content :template-ulid="$templateUlid" :type="$selectedSection['type']" :group="$content" :variant="$selectedSection['variant']"
                                :key="'content-'.$selectedSection['type'].'-'.$content.'-'.$selectedSection['variant']" />
                        </div>
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
