<div>
    @if ($person['photo'])
        @include('invitation.partials.framed-photo', [
            'src' => $person['photo'],
            'focus' => $person['photo_focus'] ?? null,
            'alt' => $person['name'] ?? '',
            'imageClass' => 'mx-auto mb-5 h-40 w-40 object-cover inv-rounded',
            'imageStyle' => 'border-radius: 9999px;',
            'wrapperClass' => filled($config['photo_frame']) ? 'mx-auto mb-5 w-60' : 'mx-auto mb-5 w-40',
        ])
    @endif
    @include('invitation.sections.couple._person-text')
</div>
