{{-- One row per person: the portrait beside the name, on the side chosen in "photo_side" (App\Support\PhotoSide). --}}
<div class="inv-container text-center">
    @if (filled($config['opening'] ?? null))
        <p lang="ar" dir="auto" class="mb-6 text-xl" @animate('opening')>{{ $config['opening'] }}</p>
    @endif
    <h2 class="inv-heading text-4xl" @animate('heading')>{{ $config['heading'] }}</h2>
    @if (filled($config['intro']))
        <p class="mx-auto mt-4 max-w-xl inv-muted" @animate('intro')>{{ $config['intro'] }}</p>
    @endif

    <div class="mx-auto mt-12 grid max-w-xl gap-12">
        @foreach ($config['people'] as $person)
            @php($right = \App\Support\PhotoSide::isRight($person['photo_side']))
            <div class="{{ \App\Support\PhotoSide::classes($person['photo_side'], filled($person['photo'])) }}" @animate('people', $loop->index)>
                @if ($person['photo'])
                    @include('invitation.partials.framed-photo', [
                        'src' => $person['photo'],
                        'focus' => $person['photo_focus'] ?? null,
                        'alt' => $person['name'] ?? '',
                        'imageClass' => 'aspect-square w-full object-cover',
                        'imageStyle' => 'border-radius: 9999px;',
                        'wrapperClass' => 'w-full',
                    ])
                @endif
                <div>
                    @include('invitation.sections.couple._person-text', [
                        'socialsClass' => 'mt-4 flex gap-2 '.(! filled($person['photo']) ? 'justify-center' : ($right ? 'justify-end' : 'justify-start')),
                    ])
                </div>
            </div>
        @endforeach
    </div>
</div>
