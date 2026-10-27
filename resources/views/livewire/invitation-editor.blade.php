<div x-data="{
        device: 'mobile',
        mobilePanel: 'preview',
        base: @js($previewUrl),
        refresh(section) {
            window.previewFrame.reload(this.$refs.frame, this.base, section);
        },
    }"
    x-on:invitation-updated.window="refresh($event.detail.section)"
    x-on:section-selected.window="window.previewFrame.scrollTo($refs.frame, $event.detail.ulid)"
    class="flex h-[calc(100svh-4rem)] flex-col overflow-clip">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-line bg-white px-4 py-3">
        <a href="{{ route('dashboard.invitations.index') }}" class="rounded-lg p-2 text-ink-soft hover:bg-sage-soft" aria-label="{{ __('Back to invitations') }}">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
        </a>
        <div class="min-w-0">
            <p class="truncate font-medium text-ink">{{ $settings['title'] }}</p>
            <p class="text-xs text-muted">
                <span wire:loading.remove>
                    @if ($savedAt)
                        <i class="fa-solid fa-check text-emerald-600" aria-hidden="true"></i> {{ __('Saved at :time', ['time' => $savedAt]) }}
                    @else
                        {{ __('All changes save automatically') }}
                    @endif
                </span>
                <span wire:loading><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> {{ __('Saving…') }}</span>
            </p>
        </div>
        <x-ui.badge :variant="$status === 'published' ? 'success' : 'neutral'">{{ ucfirst($status) }}</x-ui.badge>

        <div class="ml-auto flex flex-wrap items-center gap-2">
            <div class="hidden rounded-lg border border-line p-0.5 lg:flex" role="group" aria-label="{{ __('Preview device') }}">
                @foreach (['mobile' => 'fa-solid fa-mobile-screen', 'tablet' => 'fa-solid fa-tablet-screen-button', 'desktop' => 'fa-solid fa-desktop'] as $mode => $icon)
                    <button type="button" x-on:click="device = '{{ $mode }}'" class="rounded-md px-2.5 py-1.5 text-sm"
                        x-bind:class="device === '{{ $mode }}' ? 'bg-ink text-white' : 'text-ink-soft hover:bg-paper'" aria-label="{{ ucfirst($mode) }}">
                        <i class="{{ $icon }}" aria-hidden="true"></i>
                    </button>
                @endforeach
            </div>

            <x-ui.button :href="route('dashboard.invitations.preview', $invitation)" variant="secondary" size="sm" icon="fa-regular fa-eye" target="_blank">{{ __('Preview') }}</x-ui.button>
            <x-ui.button :href="route('dashboard.invitations.story', $invitation)" variant="secondary" size="sm" icon="fa-brands fa-instagram">{{ __('IG Story') }}</x-ui.button>
            <x-ui.button type="button" variant="secondary" size="sm" icon="fa-regular fa-floppy-disk" x-on:click="$wire.$commit().then(() => refresh())">{{ __('Save') }}</x-ui.button>

            @if ($status === 'published')
                <x-ui.button :href="$publicUrl" variant="ghost" size="sm" icon="fa-solid fa-arrow-up-right-from-square" target="_blank">{{ __('Open') }}</x-ui.button>
                <x-ui.button type="button" variant="secondary" size="sm" wire:click="unpublish" wire:loading.attr="disabled">{{ __('Unpublish') }}</x-ui.button>
            @else
                <x-ui.button type="button" variant="accent" size="sm" icon="fa-solid fa-paper-plane" wire:click="publish" wire:loading.attr="disabled" wire:target="publish">
                    <span wire:loading.remove wire:target="publish">{{ __('Publish') }}</span>
                    <span wire:loading wire:target="publish">{{ __('Publishing…') }}</span>
                </x-ui.button>
            @endif
        </div>

        @error('publish')
            <p class="w-full text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Each panel is positioned so absolute children (sr-only inputs) stay inside its scroll area; otherwise they stretch the page and the editor scrolls up, leaving a blank strip below. --}}
    <div class="relative grid min-h-0 flex-1 lg:grid-cols-[260px_minmax(0,1fr)_380px]">
        {{-- Left: sections --}}
        <aside class="relative min-h-0 overflow-y-auto border-r border-line bg-paper p-3 lg:block"
            x-bind:class="{ 'max-lg:hidden': mobilePanel !== 'sections' }">
            <livewire:section-manager :invitation-ulid="$invitationUlid" :selected="$selectedSection" wire:key="section-manager" />
        </aside>

        {{-- Center: live preview --}}
        <div class="relative flex min-h-0 items-start justify-center overflow-auto bg-[#e9e7e1] p-4 lg:p-6"
            x-bind:class="{ 'max-lg:hidden': mobilePanel !== 'preview' }">
            <div class="h-full w-full overflow-hidden rounded-2xl border border-line bg-white shadow-soft transition-all"
                x-bind:style="{ maxWidth: { mobile: '400px', tablet: '780px', desktop: '100%' }[device] }">
                <iframe x-ref="frame" src="{{ $previewUrl }}" title="{{ __('Live preview') }}" class="h-full w-full"></iframe>
            </div>
        </div>

        {{-- Right: properties (bottom sheet on mobile) --}}
        <aside class="min-h-0 overflow-y-auto border-l border-line bg-white lg:relative max-lg:fixed max-lg:inset-x-0 max-lg:bottom-14 max-lg:z-40 max-lg:max-h-[70svh] max-lg:rounded-t-2xl max-lg:shadow-2xl lg:block"
            x-bind:class="{ 'max-lg:hidden': mobilePanel !== 'edit' }">
            <div class="sticky top-0 z-10 flex border-b border-line bg-white" role="tablist">
                @foreach (array_filter(['section' => __('Section'), 'theme' => $canCustomizeTheme ? __('Theme') : null, 'music' => __('Music'), 'settings' => __('Settings')]) as $key => $label)
                    <button type="button" role="tab" wire:click="$set('panel', '{{ $key }}')"
                        @class(['flex-1 border-b-2 px-2 py-3 text-sm font-medium', 'border-ink text-ink' => $panel === $key, 'border-transparent text-muted hover:text-ink' => $panel !== $key])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="p-4">
                @if ($panel === 'section')
                    @if ($selectedSection)
                        <livewire:section-settings :invitation-ulid="$invitationUlid" :section-ulid="$selectedSection" :key="'settings-'.$selectedSection" />
                    @else
                        <x-ui.empty-state icon="fa-solid fa-layer-group" :title="__('No section selected')" :description="__('Add or pick a section from the list.')" />
                    @endif
                @elseif ($panel === 'theme' && $canCustomizeTheme)
                    @include('livewire.partials.theme-panel')
                @elseif ($panel === 'music')
                    <livewire:invitation-music :invitation-ulid="$invitationUlid" wire:key="music-panel" />
                @else
                    @include('livewire.partials.settings-panel')
                @endif
            </div>
        </aside>

        {{-- Mobile bottom navigation --}}
        <nav class="fixed inset-x-0 bottom-0 z-50 grid h-14 grid-cols-3 border-t border-line bg-white lg:hidden" aria-label="{{ __('Editor panels') }}">
            @foreach (['sections' => ['fa-solid fa-layer-group', __('Sections')], 'preview' => ['fa-regular fa-eye', __('Preview')], 'edit' => ['fa-solid fa-sliders', __('Edit')]] as $key => [$icon, $label])
                <button type="button" x-on:click="mobilePanel = '{{ $key }}'" class="flex flex-col items-center justify-center gap-0.5 text-xs"
                    x-bind:class="mobilePanel === '{{ $key }}' ? 'text-ink font-medium' : 'text-muted'">
                    <i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}
                </button>
            @endforeach
        </nav>
    </div>
</div>
