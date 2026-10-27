{{--
    Wraps one or more Livewire file inputs and shows a progress bar while their files upload.
    Livewire fires livewire-upload-* events on the input; they bubble up to this element.
--}}
<div x-data="{ uploading: false, progress: 0 }"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    x-on:livewire-upload-finish="uploading = false"
    x-on:livewire-upload-error="uploading = false"
    x-on:livewire-upload-cancel="uploading = false"
    {{ $attributes }}>
    {{ $slot }}

    <div x-show="uploading" x-cloak class="mt-2" role="progressbar" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress" aria-label="{{ __('Upload progress') }}">
        <div class="flex items-center justify-between text-xs text-muted">
            <span>{{ __('Uploading…') }}</span>
            <span class="tabular-nums" x-text="progress + '%'">0%</span>
        </div>
        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-line">
            <div class="h-full rounded-full bg-sage transition-[width] duration-200" x-bind:style="'width: ' + progress + '%'"></div>
        </div>
    </div>
</div>
