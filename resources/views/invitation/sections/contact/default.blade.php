<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>

    <div class="mx-auto mt-10 grid max-w-2xl gap-4 sm:grid-cols-2">
        @foreach ($config['contacts'] as $contact)
            <div class="inv-card p-6">
                <p class="font-semibold">{{ $contact['name'] ?? '' }}</p>
                @if (filled($contact['role'] ?? null))
                    <p class="text-sm inv-muted">{{ $contact['role'] }}</p>
                @endif
                @if ($contact['whatsapp'])
                    <a href="https://wa.me/{{ $contact['whatsapp'] }}" target="_blank" rel="noopener noreferrer" class="inv-btn mt-4">
                        <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>{{ __('Chat on WhatsApp') }}
                    </a>
                @endif
            </div>
        @endforeach
    </div>
</div>
