{{--
    Classic cover button: text, or only an icon (field "button_display"). $onPhoto: the icon sits directly on the photo.
    Its side and place come from "button_position" / "button_align" (App\Support\CoverButton); at the bottom it sits
    in the cover's bottom bar, outside the text.
--}}
@php($iconOnly = $config['button_display'] !== 'label')
@php($atBottom = \App\Support\CoverButton::atBottom($config['button_position'] ?? null))

@if ($iconOnly || filled($config['button_label']))
    <button type="button" @class([\App\Support\CoverButton::classes($config['button_position'] ?? null, $config['button_align'] ?? null, $config['alignment'] ?? 'center'), 'mt-10' => ! $atBottom, 'inv-btn' => ! $iconOnly, 'inv-cue' => $iconOnly, 'inv-cue--on-photo' => $iconOnly && $onPhoto]) @animate('button_label') x-data
        @if ($iconOnly) aria-label="{{ $config['button_label'] ?: __('Open Invitation') }}" @endif
        x-on:click="$el.closest('section').nextElementSibling?.scrollIntoView({ behavior: 'smooth' })">
        @if ($iconOnly)
            @include('invitation.partials.cover-cue', ['cue' => $config['button_display']])
        @else
            <i class="fa-regular fa-envelope-open" aria-hidden="true"></i>{{ $config['button_label'] }}
        @endif
    </button>
@endif
