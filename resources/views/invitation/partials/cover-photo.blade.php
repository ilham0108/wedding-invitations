{{--
    The cover's own photo (cover "photo", "photo_focus"), shaped or framed by the shared framed-photo partial
    ("photo_shape", "photo_frame", "photo_frame_inset"), styled by "photo_width", "photo_tilt",
    "photo_border_width", "photo_border_color", "photo_shadow" and "photo_opacity". The edges switched on in
    "photo_fade_top" / "_bottom" / "_left" / "_right" fade out over "photo_fade_size" percent: one mask
    gradient per edge, intersected (invitation.css "Cover photo"), so two neighbouring edges fade a corner.

    Each cover includes this twice: $where = 'flow' inside the text column (placement "above") and
    $where = 'free' on the cover itself (placement "free", centred on "photo_x" / "photo_y" percent of the
    cover, on the "photo_layer" behind or in front of the text). Only the matching one renders.
    In the editors, data-inv-cover-photo-free (the section key: $sectionKey, else $section['key']) lets
    cover-photo-drag.js move it in the preview while that same section's settings are open.
    It is on the first screen, so it loads eagerly. $alt names the people in it.
--}}
@php
    $where ??= 'flow';
    $free = ($config['photo_placement'] ?? 'above') === 'free';
    $border = (float) ($config['photo_border_width'] ?? 0);
    $tilt = (float) ($config['photo_tilt'] ?? 0);
    $opacity = (float) ($config['photo_opacity'] ?? 100);
    $fadeSize = (float) ($config['photo_fade_size'] ?? 35);
    // Each faded edge: a gradient from transparent at that edge to opaque, running inwards.
    $fades = collect(['photo_fade_top' => 'to bottom', 'photo_fade_bottom' => 'to top', 'photo_fade_left' => 'to right', 'photo_fade_right' => 'to left'])
        ->filter(fn (string $direction, string $field): bool => (bool) ($config[$field] ?? false))
        ->map(fn (string $direction): string => "linear-gradient({$direction}, transparent, #000 {$fadeSize}%)")
        ->implode(', ');
    $style = collect([
        '--inv-cover-photo-width: '.($config['photo_width'] ?? 50).';',
        $tilt != 0 ? "rotate: {$tilt}deg;" : null,
        $border > 0 ? "--inv-cover-photo-border: {$border}px solid ".($config['photo_border_color'] ?? '#ffffff').';' : null,
        $opacity < 100 ? '--inv-cover-photo-opacity: '.($opacity / 100).';' : null,
        $fades !== '' ? "--inv-cover-photo-mask: {$fades};" : null,
        $free ? 'left: '.($config['photo_x'] ?? 50).'%; top: '.($config['photo_y'] ?? 30).'%;' : null,
    ])->filter()->implode(' ');
@endphp

@if (filled($config['photo'] ?? null) && $free === ($where === 'free'))
    <div @class([
        'inv-cover-photo',
        'inv-cover-photo--free inv-cover-photo--'.($config['photo_layer'] ?? 'back') => $free,
        'inv-cover-photo--start' => ! $free && ($config['alignment'] ?? 'center') === 'left',
        'inv-cover-photo--shadow-'.($config['photo_shadow'] ?? 'none') => ($config['photo_shadow'] ?? 'none') !== 'none',
        'inv-cover-photo--faded' => $fades !== '',
    ]) style="{{ $style }}" @if ($free) data-inv-cover-photo-free="{{ $sectionKey ?? ($section['key'] ?? '') }}" @endif @animate('photo')>
        @include('invitation.partials.framed-photo', [
            'src' => $config['photo'],
            'focus' => $config['photo_focus'] ?? null,
            'alt' => $alt,
            'imageClass' => 'inv-cover-photo__image',
            'wrapperClass' => '',
            'loading' => 'eager',
        ])
    </div>
@endif
