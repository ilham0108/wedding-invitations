{{-- The icon of an icon-only cover button ($cue: "scroll" or "arrow"); the button carries the accessible name. --}}
@if ($cue === 'scroll')
    <span class="inv-cue__mouse" aria-hidden="true"><span></span></span>
@else
    <svg class="inv-cue__arrow" viewBox="0 0 28 14" width="28" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 2l12 10L26 2"/></svg>
@endif
