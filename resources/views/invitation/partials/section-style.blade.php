{{--
    A section's own fonts and colors (SectionFonts, SectionColors; builder Section panel): the CSS variables, plus the
    attributes invitation.css ("Section fonts", "Section colors") reads. $withStyle = false when the element writes its
    own style attribute and appends $section['section_style'] itself.
    data-inv-own-anim: the section animates its elements one by one (SectionAnimations), not as blocks.
--}}
@if (! empty($section['animations'])) data-inv-own-anim @endif
@if (($section['section_style'] ?? '') !== '')
    @if ($withStyle ?? true) style="{{ $section['section_style'] }}" @endif
    @isset($section['fonts']['font_body']) data-inv-body-font @endisset
    @isset($section['fonts']['heading_weight']) data-inv-heading-weight="{{ $section['fonts']['heading_weight'] }}" @endisset
    @isset($section['fonts']['body_weight']) data-inv-body-weight="{{ $section['fonts']['body_weight'] }}" @endisset
    @isset($section['colors']['heading']) data-inv-heading-color @endisset
    @isset($section['colors']['text']) data-inv-text-color @endisset
    @isset($section['colors']['social_icon']) data-inv-social-icon @endisset
    @isset($section['colors']['social_bg']) data-inv-social-bg @endisset
@endif
