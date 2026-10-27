<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]"
    x-data="{
        card: {{ json_encode($card) }},
        options: {{ json_encode($options) }},
        busy: false,
        saved: false,
        canShare: false,
        saveTimer: null,
        async redraw() {
            if (! window.InvitationStory) { setTimeout(() => this.redraw(), 50); return; }
            await window.InvitationStory.draw(this.$refs.canvas, this.card, this.options);
        },
        persist() {
            clearTimeout(this.saveTimer);
            this.saved = false;
            this.saveTimer = setTimeout(async () => { await $wire.save(this.options); this.saved = true }, 600);
        },
        async download() {
            this.busy = true;
            try { await window.InvitationStory.download(this.$refs.canvas, this.card, this.options) } finally { this.busy = false }
        },
        async share() {
            this.busy = true;
            try { if (! await window.InvitationStory.share(this.$refs.canvas, this.card, this.options)) this.canShare = false } finally { this.busy = false }
        },
        init() {
            this.canShare = typeof navigator.canShare === 'function' && /Android|iPhone|iPad/i.test(navigator.userAgent);
            this.redraw();
            this.$watch('options', () => { this.redraw(); this.persist() });
        },
    }">
    <x-ui.card padding="p-4 sm:p-6">
        <div class="flex justify-center rounded-xl bg-[#e9e7e1] p-4" wire:ignore>
            <canvas x-ref="canvas" width="{{ $card['width'] }}" height="{{ $card['height'] }}" role="img" aria-label="{{ __('Instagram Story preview') }}"
                class="h-auto max-h-[72svh] w-auto max-w-full rounded-lg shadow-soft" style="aspect-ratio: 9 / 16"></canvas>
        </div>
    </x-ui.card>

    <div class="space-y-4">
        <x-ui.card :title="__('Layout')">
            <div class="grid grid-cols-2 gap-2">
                @foreach (['photo' => [__('Photo'), 'fa-regular fa-image'], 'minimal' => [__('Minimal'), 'fa-solid fa-border-all']] as $key => [$label, $icon])
                    <button type="button" x-on:click="options.layout = '{{ $key }}'"
                        class="flex flex-col items-center gap-1 rounded-lg border px-2 py-3 text-sm"
                        x-bind:class="options.layout === '{{ $key }}' ? 'border-ink bg-paper font-medium text-ink' : 'border-line text-ink-soft hover:border-ink/30'">
                        <i class="{{ $icon }}" aria-hidden="true"></i>{{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="mt-4 space-y-2 text-sm text-ink-soft">
                @if ($card['photo'])
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="options.photo" class="rounded border-line text-sage focus:ring-sage"> {{ __('Cover photo') }}</label>
                @endif
                @if (filled($card['venue']))
                    <label class="flex items-center gap-2"><input type="checkbox" x-model="options.venue" class="rounded border-line text-sage focus:ring-sage"> {{ __('Venue') }}</label>
                @endif
                <label class="flex items-center gap-2"><input type="checkbox" x-model="options.link" class="rounded border-line text-sage focus:ring-sage"> {{ __('Invitation link') }}</label>
            </div>
        </x-ui.card>

        <x-ui.card :title="__('Text')">
            <div class="space-y-4">
                <div>
                    <label for="story-headline" class="block text-sm font-medium text-ink">{{ __('Headline') }}</label>
                    <input id="story-headline" type="text" maxlength="{{ $maxHeadline }}" x-model.debounce.400ms="options.headline"
                        placeholder="{{ $card['subtitle'] ?: __('e.g. Save the Date') }}"
                        class="mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($headlinePresets as $preset)
                            <button type="button" x-on:click="options.headline = @js($preset)"
                                class="rounded-full border border-line px-2.5 py-1 text-xs text-ink-soft hover:border-ink/30 hover:text-ink">{{ $preset }}</button>
                        @endforeach
                    </div>
                    <p class="mt-1.5 text-xs text-muted">{{ __('Leave empty to use the subtitle of your cover.') }}</p>
                </div>

                <div>
                    <label for="story-message" class="block text-sm font-medium text-ink">{{ __('Message (optional)') }}</label>
                    <textarea id="story-message" rows="2" maxlength="{{ $maxMessage }}" x-model.debounce.400ms="options.message"
                        placeholder="{{ __('e.g. We would be honoured by your presence') }}"
                        class="mt-1.5 block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage"></textarea>
                </div>
            </div>
            <p class="mt-4 text-xs text-muted">{{ __('Names, date and photo come from the Cover and Event sections of your invitation.') }}</p>
        </x-ui.card>

        <x-ui.card>
            <label class="flex items-start justify-between gap-3">
                <span>
                    <span class="block text-sm font-medium text-ink">{{ __('Button for guests') }}</span>
                    <span class="mt-0.5 block text-xs text-muted">{{ __('Guests can download this image from your invitation page to share it in their own story.') }}</span>
                </span>
                <input type="checkbox" x-model="options.guest_button" class="mt-1 rounded border-line text-sage focus:ring-sage">
            </label>
            <p class="mt-3 text-xs text-muted" x-show="saved" x-cloak><i class="fa-solid fa-check text-emerald-600" aria-hidden="true"></i> {{ __('Settings saved') }}</p>
        </x-ui.card>

        <x-ui.card>
            <div class="space-y-2">
                <x-ui.button type="button" variant="accent" class="w-full" icon="fa-solid fa-download" x-on:click="download()" x-bind:disabled="busy">{{ __('Download image') }}</x-ui.button>
                <template x-if="canShare">
                    <x-ui.button type="button" variant="secondary" class="w-full" icon="fa-solid fa-share-nodes" x-on:click="share()" x-bind:disabled="busy">{{ __('Share') }}</x-ui.button>
                </template>
            </div>
            <p class="mt-3 text-xs text-muted">
                @if ($invitation->isPublished())
                    {{ __('Post it as an Instagram Story and add a link sticker to :link so guests can open your invitation.', ['link' => $card['link']]) }}
                @else
                    {{ __('Publish your invitation first so the link on the image works for guests.') }}
                @endif
            </p>
        </x-ui.card>
    </div>
</div>
