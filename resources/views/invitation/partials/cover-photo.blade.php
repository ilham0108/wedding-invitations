{{--
    The cover's own photo (cover "photo", "photo_focus"), shaped or framed by the shared framed-photo partial
    ("photo_shape", "photo_frame", "photo_frame_inset"), styled by "photo_width", "photo_tilt",
    "photo_border_width", "photo_border_color" and "photo_shadow".

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
    $style = collect([
        '--inv-cover-photo-width: '.($config['photo_width'] ?? 50).';',
        $tilt != 0 ? "rotate: {$tilt}deg;" : null,
        $border > 0 ? "--inv-cover-photo-border: {$border}px solid ".($config['photo_border_color'] ?? '#ffffff').';' : null,
        $free ? 'left: '.($config['photo_x'] ?? 50).'%; top: '.($config['photo_y'] ?? 30).'%;' : null,
    ])->filter()->implode(' ');
@endphp

@if (filled($config['photo'] ?? null) && $free === ($where === 'free'))
    <div @class([
        'inv-cover-photo',
        'inv-cover-photo--free inv-cover-photo--'.($config['photo_layer'] ?? 'back') => $free,
        'inv-cover-photo--start' => ! $free && ($config['alignment'] ?? 'center') === 'left',
        'inv-cover-photo--shadow-'.($config['photo_shadow'] ?? 'none') => ($config['photo_shadow'] ?? 'none') !== 'none',
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
