{{-- The cover's scroll button: a mouse with a label, or only an icon (field "button_display"), placed by App\Support\CoverButton. --}}
@php($iconOnly = $config['button_display'] !== 'label')
<a href="#" @class(['bc-scroll inv-animate', \App\Support\CoverButton::classes($config['button_position'] ?? null, $config['button_align'] ?? null, $config['alignment'] ?? 'center', designBottom: true), 'inv-cue' => $iconOnly]) @animate('button_label') data-bc-scroll @if ($iconOnly) aria-label="{{ $config['button_label'] ?: __('Scroll down') }}" @endif>
    @if ($iconOnly)
        @include('invitation.partials.cover-cue', ['cue' => $config['button_display']])
    @else
        <span class="bc-scroll__mouse" aria-hidden="true"><span></span></span>
        <span class="bc-scroll__label">{{ $config['button_label'] ?: __('Scroll down') }}</span>
    @endif
</a>
