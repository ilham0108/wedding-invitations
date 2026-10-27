{{--
    Guest button (owner's choice, invitation_settings.story.guest_button): opens a dialog with the
    invitation's Instagram Story image, drawn by resources/js/story.js with the owner's layout and text.
--}}
@vite('resources/js/story.js')

<div x-data="{
        card: {{ json_encode($story['card']) }},
        options: {{ json_encode($story['options']) }},
        busy: false,
        drawn: false,
        canShare: typeof navigator.canShare === 'function' && /Android|iPhone|iPad/i.test(navigator.userAgent),
        async open() {
            this.$refs.dialog.showModal();
            if (this.drawn) return;
            while (! window.InvitationStory) await new Promise((resolve) => setTimeout(resolve, 50));
            await window.InvitationStory.draw(this.$refs.canvas, this.card, this.options);
            this.drawn = true;
        },
        async run(action) {
            this.busy = true;
            try {
                const shared = await window.InvitationStory[action](this.$refs.canvas, this.card, this.options);
                if (action === 'share' && ! shared) this.canShare = false;
            } finally { this.busy = false }
        },
    }">
    <button type="button" class="inv-fab" x-on:click="open()" aria-label="{{ __('Share to Instagram Story') }}" title="{{ __('Share to Instagram Story') }}">
        <i class="fa-brands fa-instagram" aria-hidden="true"></i>
    </button>

    <dialog x-ref="dialog" class="inv-story-dialog" aria-label="{{ __('Instagram Story') }}" x-on:click.self="$el.close()">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm font-semibold">{{ __('Share to Instagram Story') }}</p>
            <button type="button" x-on:click="$refs.dialog.close()" class="rounded-full px-2 py-1 text-lg leading-none inv-muted" aria-label="{{ __('Close') }}">&times;</button>
        </div>

        <canvas x-ref="canvas" width="{{ $story['card']['width'] }}" height="{{ $story['card']['height'] }}" role="img" aria-label="{{ __('Instagram Story preview') }}"></canvas>

        <div class="mt-3 grid gap-2" x-bind:class="canShare ? 'grid-cols-2' : 'grid-cols-1'">
            <button type="button" class="inv-btn justify-center" x-on:click="run('download')" x-bind:disabled="busy">
                <i class="fa-solid fa-download" aria-hidden="true"></i>{{ __('Download image') }}
            </button>
            <template x-if="canShare">
                <button type="button" class="inv-btn justify-center" x-on:click="run('share')" x-bind:disabled="busy">
                    <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>{{ __('Share') }}
                </button>
            </template>
        </div>
        <p class="mt-2 text-center text-xs inv-muted">{{ __('Post it as your Instagram Story to share the news.') }}</p>
    </dialog>
</div>
