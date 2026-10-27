<x-layouts.app area="dashboard" :title="__('Choose a payment method')">
    <x-slot:actions>
        <x-ui.button :href="route('dashboard.subscription.index')" variant="secondary" icon="fa-solid fa-arrow-left">{{ __('Back to plans') }}</x-ui.button>
    </x-slot:actions>

    <div class="mx-auto max-w-2xl space-y-6">
        <x-ui.card>
            <p class="text-sm text-muted">{{ __('You are buying') }}</p>
            <p class="mt-1 text-lg font-semibold text-ink">{{ $price->plan->name }}</p>
            <p class="text-sm text-ink-soft">{{ \App\Support\Money::format($price->amount, $price->currency) }} · {{ $price->periodLabel() }}</p>
        </x-ui.card>

        @error('method')
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $message }}</div>
        @enderror

        <form method="POST" action="{{ route('dashboard.subscription.checkout') }}" class="space-y-6" x-data="{ method: '' }">
            @csrf
            <input type="hidden" name="price" value="{{ $price->ulid }}">

            @foreach ($methods as $group => $channels)
                <fieldset>
                    <legend class="mb-2 text-sm font-medium text-ink">{{ $group }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($channels as $channel)
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border bg-white px-4 py-3 text-sm transition"
                                x-bind:class="method === @js($channel['code']) ? 'border-sage ring-2 ring-sage/30' : 'border-line hover:border-ink/30'">
                                <input type="radio" name="method" value="{{ $channel['code'] }}" x-model="method" class="text-sage focus:ring-sage" required>
                                @if ($channel['icon_url'])
                                    <img src="{{ $channel['icon_url'] }}" alt="" loading="lazy" class="h-6 w-14 object-contain">
                                @endif
                                <span class="text-ink">{{ $channel['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <x-ui.button class="w-full" size="lg" icon="fa-solid fa-lock" x-bind:disabled="method === ''">{{ __('Continue to payment') }}</x-ui.button>
            <p class="text-center text-xs text-muted">{{ __('You will be taken to the secure payment page to finish.') }}</p>
        </form>
    </div>
</x-layouts.app>
