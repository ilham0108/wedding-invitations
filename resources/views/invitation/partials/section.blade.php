{{-- One rendered section with its anchor, background and data attributes. Shared by the classic page and design pages. --}}
<section id="section-{{ $section['key'] }}" data-section="{{ $section['type'] }}"
    @include('invitation.partials.backdrop-attributes')
    @include('invitation.partials.section-style')
    @class([
        $sectionClass ?? '',
        'inv-has-ornaments' => ! empty($section['ornaments']),
        'inv-section--screen inv-section--'.($section['height'] ?? 'auto') => ($section['height'] ?? 'auto') !== 'auto',
        'inv-heading-size--'.($section['heading_size'] ?? 'default') => ($section['heading_size'] ?? 'default') !== 'default',
        'inv-text-size--'.($section['text_size'] ?? 'default') => ($section['text_size'] ?? 'default') !== 'default',
        'inv-section' => $section['type'] !== 'cover' && ($classic ?? true),
        'inv-animate' => $section['type'] !== 'cover' && ($classic ?? true),
        'inv-section--muted' => $section['background'] === 'muted' && ($classic ?? true),
        'inv-section--primary' => $section['background'] === 'primary' && ($classic ?? true),
    ])>
    {{-- The body sits between the back and the front ornament layers. --}}
    <div class="inv-section-body">
        @include($section['view'], ['config' => $section['config'], 'variant' => $section['variant']])
    </div>
    @include('invitation.partials.ornaments', ['ornaments' => $section['ornaments']])
</section>
