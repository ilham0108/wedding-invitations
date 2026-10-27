{{--
    Attributes of a section with a background photo (section fields "background_photo" and "background_overlay").
    data-inv-backdrop: the photo invitation.js shows behind the page while this section is on screen.
    data-inv-backdrop-overlay: how much of the page overlay covers that photo.
    A section that paints its own color (Latar "muted"/"primary", or a type in $solidTypes for designs that
    always color it) is tinted itself instead (data-inv-tint), and the page overlay is switched off for it.
--}}
@if (filled($section['config']['background_photo'] ?? null))
    @php
        $overlay = $section['config']['background_overlay'] ?? 'strong';
        $tinted = $section['background'] !== 'default' || in_array($section['type'], $solidTypes ?? [], true);
    @endphp
    data-inv-backdrop="{{ $section['config']['background_photo'] }}"
    data-inv-backdrop-overlay="{{ $tinted ? 'none' : $overlay }}"
    @if ($tinted) data-inv-tint="{{ $overlay }}" @endif
@endif
