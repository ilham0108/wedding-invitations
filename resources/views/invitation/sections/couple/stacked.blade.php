<div class="inv-container text-center">
    @if (filled($config['opening'] ?? null))
        <p lang="ar" dir="auto" class="mb-6 text-xl">{{ $config['opening'] }}</p>
    @endif
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['intro']))
        <p class="mx-auto mt-4 max-w-xl inv-muted">{{ $config['intro'] }}</p>
    @endif

    <div class="mx-auto mt-12 grid max-w-md gap-12">
        @foreach ($config['people'] as $person)
            @include('invitation.sections.couple._person')
        @endforeach
    </div>
</div>
