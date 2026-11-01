{{-- Plan price for the marketing pricing table ($compact: narrower type) and cards. The period sits on its own line so it never breaks mid-phrase. --}}
@php($size = ($compact ?? false) ? 'text-[2rem]' : 'text-[2.4rem]')
<div class="mt-5">
    @if ($plan->is_free || $plan->activePrices->isEmpty())
        <p class="mk-serif {{ $size }} whitespace-nowrap leading-none text-ink">{{ \App\Support\Money::format(0) }}</p>
    @else
        @foreach ($plan->activePrices as $price)
            @if ($loop->first)
                <p class="mk-serif {{ $size }} whitespace-nowrap leading-none text-ink">{{ \App\Support\Money::format($price->amount, $price->currency) }}</p>
                <p class="mt-2 text-sm text-ink-soft">{{ $price->periodLabel() }}</p>
            @else
                <p class="mt-1 text-sm text-ink-soft">{{ __('or') }} <span class="whitespace-nowrap font-semibold text-ink">{{ \App\Support\Money::format($price->amount, $price->currency) }}</span> {{ $price->periodLabel() }}</p>
            @endif
        @endforeach
    @endif
</div>
