{{--
    The cover's own photo above the names (cover "photo", "photo_focus", "photo_size"), shaped or framed
    by the shared framed-photo partial ("photo_shape", "photo_frame", "photo_frame_inset").
    It is on the first screen, so it loads eagerly. $alt names the people in it.
--}}
@if (filled($config['photo'] ?? null))
    <div @class([
        'inv-cover-photo',
        'inv-cover-photo--'.($config['photo_size'] ?? 'medium'),
        'inv-cover-photo--start' => ($config['alignment'] ?? 'center') === 'left',
    ]) @animate('photo')>
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
