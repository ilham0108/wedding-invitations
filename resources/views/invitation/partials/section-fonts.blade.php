{{--
    A section's own fonts (App\Services\Invitation\SectionFonts, builder Section panel): the CSS variables, plus the
    attributes invitation.css ("Section fonts") reads. $withStyle = false when the element writes its own style
    attribute and appends $section['font_style'] itself.
--}}
@if (($section['font_style'] ?? '') !== '')
    @if ($withStyle ?? true) style="{{ $section['font_style'] }}" @endif
    @isset($section['fonts']['font_body']) data-inv-body-font @endisset
    @isset($section['fonts']['heading_weight']) data-inv-heading-weight="{{ $section['fonts']['heading_weight'] }}" @endisset
    @isset($section['fonts']['body_weight']) data-inv-body-weight="{{ $section['fonts']['body_weight'] }}" @endisset
@endif
