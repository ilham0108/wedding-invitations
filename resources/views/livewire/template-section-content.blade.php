<div class="space-y-5">
    <p class="flex items-center justify-between gap-3 text-xs text-muted">
        <span>
            @if ($group === 'default_configuration')
                {{ __('Copied into every new invitation made from this template. Clients replace it with their own content.') }}
            @else
                {{ __('Shown only on the template preview, so clients can imagine their invitation.') }}
            @endif
        </span>
        <span class="shrink-0">
            <span wire:loading.remove>@if ($savedAt)<i class="fa-solid fa-check text-emerald-600" aria-hidden="true"></i>@endif</span>
            <span wire:loading><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i></span>
        </span>
    </p>

    @error('config')
        <p class="text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror

    @foreach ($fields as $field)
        @include('livewire.partials.section-field', ['field' => $field, 'path' => $field['key']])
    @endforeach

    @include('livewire.partials.illustration-picker')
</div>
