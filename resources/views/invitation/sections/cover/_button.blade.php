{{-- Classic cover button: text, or only an icon (field "button_display"). $onPhoto: the icon sits directly on the photo. --}}
@php($iconOnly = $config['button_display'] !== 'label')

@if ($iconOnly || filled($config['button_label']))
    <button type="button" @class(['mt-10', 'inv-btn' => ! $iconOnly, 'inv-cue' => $iconOnly, 'inv-cue--on-photo' => $iconOnly && $onPhoto]) @animate('button_label') x-data
        @if ($iconOnly) aria-label="{{ $config['button_label'] ?: __('Open Invitation') }}" @endif
        x-on:click="$el.closest('section').nextElementSibling?.scrollIntoView({ behavior: 'smooth' })">
        @if ($iconOnly)
            @include('invitation.partials.cover-cue', ['cue' => $config['button_display']])
        @else
            <i class="fa-regular fa-envelope-open" aria-hidden="true"></i>{{ $config['button_label'] }}
        @endif
    </button>
@endif
