<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2">
        <x-ui.stat-card :label="__('Revenue this month')" :value="\App\Support\Money::format($revenueThisMonth)" icon="fa-solid fa-sack-dollar" />
        <x-ui.stat-card :label="__('Awaiting payment')" :value="Number::format($pendingCount)" icon="fa-regular fa-hourglass" />
    </div>


    <x-ui.card padding="p-0">
        <div class="flex flex-wrap gap-2 border-b border-line p-4">
            <input type="search" wire:model.live.debounce.400ms="search" aria-label="{{ __('Search') }}"
                placeholder="{{ $tab === 'payments' ? __('Search payment id') : __('Search order number, name, or email') }}"
                class="h-9 min-w-[14rem] flex-1 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
            <select wire:model.live="status" aria-label="{{ __('Filter by status') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>

        @if ($rows->isEmpty())
            <x-ui.empty-state icon="fa-solid fa-receipt" :title="$tab === 'payments' ? __('No payments yet.') : __('No orders yet.')" />
        @elseif ($tab === 'payments')
            <ul class="divide-y divide-line">
                @foreach ($rows as $payment)
                    <li wire:key="payment-{{ $payment->ulid }}" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">{{ $payment->order->number }} <span class="font-normal text-muted">· {{ $payment->order->user->email }}</span></p>
                            <p class="text-xs text-muted">{{ ucfirst($payment->gateway) }} · {{ $payment->external_id }}@if ($payment->method) · {{ $payment->method }}@endif · {{ $payment->created_at->translatedFormat('d M Y, H:i') }}</p>
                        </div>
                        <span class="tabular-nums text-ink">{{ \App\Support\Money::format($payment->paid_amount ?? $payment->amount) }}</span>
                        <x-ui.badge :variant="$payment->status->badgeVariant()">{{ $payment->status->label() }}</x-ui.badge>
                    </li>
                @endforeach
            </ul>
        @else
            <ul class="divide-y divide-line">
                @foreach ($rows as $order)
                    <li wire:key="order-{{ $order->ulid }}" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">{{ $order->number }} · {{ $order->plan->name }}</p>
                            <p class="text-xs text-muted">{{ $order->user->name }} ({{ $order->user->email }}) · {{ $order->billing_period->label() }} · {{ $order->created_at->translatedFormat('d M Y, H:i') }}@if ($order->latestPayment) · {{ ucfirst($order->latestPayment->gateway) }}@endif</p>
                        </div>
                        <span class="tabular-nums text-ink">{{ \App\Support\Money::format($order->amount, $order->currency) }}</span>
                        <x-ui.badge :variant="$order->status->badgeVariant()">{{ $order->status->label() }}</x-ui.badge>
                        @if ($order->status->value === 'pending')
                            <div class="flex gap-1">
                                @if ($order->latestPayment?->gateway === 'manual')
                                    <x-ui.button type="button" size="sm" wire:click="confirmPayment('{{ $order->ulid }}')"
                                        data-confirm-variant="default" data-confirm-button="{{ __('Confirm payment') }}" data-confirm="{{ __('Confirm that :amount was received for :number? The plan will be activated.', ['amount' => \App\Support\Money::format($order->amount), 'number' => $order->number]) }}">{{ __('Confirm payment') }}</x-ui.button>
                                @endif
                                <x-ui.button type="button" variant="ghost" size="sm" class="text-red-600" wire:click="cancelOrder('{{ $order->ulid }}')" data-confirm-button="{{ __('Yes, cancel') }}" data-confirm="{{ __('Cancel this order?') }}">{{ __('Cancel') }}</x-ui.button>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        <div class="border-t border-line px-4 py-3">{{ $rows->links() }}</div>
    </x-ui.card>
</div>
