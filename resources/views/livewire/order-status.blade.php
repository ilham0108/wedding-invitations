<div class="mx-auto max-w-xl space-y-6" @if ($pending) wire:poll.5s="refreshStatus" @endif>
    <x-ui.card>
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-muted">{{ __('Order') }} {{ $order->number }}</p>
                <p class="mt-1 text-xl font-semibold text-ink">{{ $order->plan->name }}</p>
                <p class="text-sm text-ink-soft">{{ \App\Support\Money::format($order->amount, $order->currency) }} · {{ $order->billing_period->label() }}</p>
            </div>
            <x-ui.badge :variant="$order->status->badgeVariant()">{{ $order->status->label() }}</x-ui.badge>
        </div>

        @if ($order->status->value === 'paid')
            <div class="mt-6 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-700" role="status">
                <i class="fa-regular fa-circle-check mr-1" aria-hidden="true"></i>
                {{ __('Payment received. Your :plan plan is active until :date.', ['plan' => $order->plan->name, 'date' => $order->subscription?->ends_at?->translatedFormat('d F Y') ?? '—']) }}
            </div>
            <x-ui.button :href="route('dashboard')" class="mt-4">{{ __('Back to dashboard') }}</x-ui.button>
        @elseif ($pending)
            @if ($payment?->checkout_url)
                <p class="mt-6 text-sm text-ink-soft">{{ __('Complete the payment on the secure payment page. This page updates automatically once the payment is confirmed.') }}</p>
                <x-ui.button :href="$payment->checkout_url" variant="accent" class="mt-4" icon="fa-solid fa-lock">{{ __('Continue to payment') }}</x-ui.button>
            @elseif ($manualInstructions)
                <div class="mt-6 rounded-lg bg-paper px-4 py-3 text-sm text-ink-soft">
                    <p class="font-medium text-ink">{{ __('How to pay') }}</p>
                    <p class="mt-1 whitespace-pre-line">{{ $manualInstructions }}</p>
                    <p class="mt-2">{{ __('Mention order number :number in the transfer note.', ['number' => $order->number]) }}</p>
                </div>
            @endif
            @if ($order->expires_at)
                <p class="mt-3 text-xs text-muted">{{ __('Pay before :time.', ['time' => $order->expires_at->timezone($timezone)->translatedFormat('d F Y, H:i')]) }}</p>
            @endif
            <p class="mt-3 text-xs text-muted"><i class="fa-solid fa-spinner fa-spin mr-1" aria-hidden="true"></i>{{ __('Waiting for payment confirmation…') }}</p>
            <button type="button" wire:click="cancel" data-confirm="{{ __('Cancel this order?') }}" data-confirm-button="{{ __('Yes, cancel') }}" class="mt-4 text-sm text-red-600 hover:underline">{{ __('Cancel order') }}</button>
        @else
            <p class="mt-6 text-sm text-ink-soft">{{ __('This order was not paid. You can start a new checkout from the plans page.') }}</p>
            <x-ui.button :href="route('dashboard.subscription.index')" variant="secondary" class="mt-4">{{ __('See plans') }}</x-ui.button>
        @endif
    </x-ui.card>
</div>
