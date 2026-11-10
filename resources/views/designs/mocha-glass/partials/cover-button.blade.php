{{-- The cover layer's open button ($cover): text, or only an icon (field "button_display"), placed by App\Support\CoverButton. --}}
@php($iconOnly = $cover['config']['button_display'] !== 'label')
<button type="button" @class(['mg-btn-open', \App\Support\CoverButton::classes($cover['config']['button_position'] ?? null, $cover['config']['button_align'] ?? null, $cover['config']['alignment'] ?? 'center'), 'inv-cue inv-cue--on-photo' => $iconOnly]) @animate('button_label') data-mg-open @if ($iconOnly) aria-label="{{ $cover['config']['button_label'] ?: __('Open Invitation') }}" @endif>
    @if ($iconOnly)
        @include('invitation.partials.cover-cue', ['cue' => $cover['config']['button_display']])
    @else
        <i class="fa-regular fa-envelope-open" aria-hidden="true"></i>{{ $cover['config']['button_label'] ?: __('Open Invitation') }}
    @endif
</button>
