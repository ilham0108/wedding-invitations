@props(['model', 'variables', 'id' => 'message_body', 'rows' => 10])

{{-- Textarea with variable insertion buttons (rules.md section 40). Inserts at the cursor position. --}}
<div x-data="{
        insert(variable) {
            const el = this.$refs.body;
            const token = '{' + '{' + variable + '}' + '}';
            const start = el.selectionStart ?? el.value.length;
            const end = el.selectionEnd ?? el.value.length;
            el.value = el.value.slice(0, start) + token + el.value.slice(end);
            el.dispatchEvent(new Event('input'));
            el.focus();
            el.setSelectionRange(start + token.length, start + token.length);
        }
    }" class="space-y-2">
    <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('Insert variable') }}">
        @foreach ($variables as $variable => $label)
            <button type="button" x-on:click="insert(@js($variable))"
                class="rounded-full border border-line bg-white px-2.5 py-1 text-xs text-ink-soft hover:border-sage hover:text-ink">
                <i class="fa-solid fa-plus mr-0.5 text-[9px]" aria-hidden="true"></i>{{ $label }}
            </button>
        @endforeach
    </div>
    <textarea id="{{ $id }}" x-ref="body" rows="{{ $rows }}" wire:model.live.debounce.500ms="{{ $model }}" maxlength="{{ \App\Services\WhatsApp\MessageRenderer::MAX_LENGTH }}"
        class="block w-full rounded-lg border-line font-mono text-sm focus:border-sage focus:ring-sage"></textarea>
    <p class="text-xs text-muted">{{ __('Use *bold* and _italic_ like in WhatsApp. Variables are replaced for each guest.') }}</p>
</div>
