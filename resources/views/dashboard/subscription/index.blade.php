<x-layouts.app area="dashboard" :title="__('Subscription')">
    <div class="space-y-8">
        @error('price')
            <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $message }}</div>
        @enderror

        @if ($offlineInvitations->isNotEmpty())
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900" role="alert">
                <p class="font-medium"><i class="fa-solid fa-triangle-exclamation mr-1" aria-hidden="true"></i>{{ trans_choice(':count invitation is offline|:count invitations are offline', $offlineInvitations->count()) }}</p>
                <p class="mt-1">{{ __('Guests cannot open them on your current plan:') }} {{ $offlineInvitations->pluck('title')->join(', ') }}. {{ __('Upgrade or renew to bring them back online.') }}</p>
            </div>
        @endif

        {{-- Current plan and usage --}}
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
            <x-ui.card :title="__('Current plan')">
                <p class="font-display text-3xl text-ink">{{ $currentPlan?->name ?? __('Free') }}</p>
                @if ($subscription)
                    <p class="mt-1 text-sm text-ink-soft">
                        {{ $subscription->billing_period->label() }} ·
                        {{ $subscription->ends_at ? __('active until :date', ['date' => $subscription->ends_at->translatedFormat('d F Y')]) : __('no end date') }}
                    </p>
                @else
                    <p class="mt-1 text-sm text-ink-soft">{{ __('Free forever. Upgrade anytime for more invitations, guests, and messages.') }}</p>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Usage')">
                <ul class="space-y-4">
                    @foreach ($usage as $meter)
                        <li>
                            <div class="flex items-baseline justify-between text-sm">
                                <span class="text-ink-soft">{{ $meter['label'] }}</span>
                                <span class="tabular-nums font-medium text-ink">{{ Number::format($meter['used']) }} / {{ $meter['limit'] === null ? __('Unlimited') : Number::format($meter['limit']) }}</span>
                            </div>
                            @if ($meter['limit'] !== null)
                                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-sage-soft" role="meter" aria-valuemin="0" aria-valuemax="{{ $meter['limit'] }}" aria-valuenow="{{ $meter['used'] }}" aria-label="{{ $meter['label'] }}">
                                    <div @class(['h-full rounded-full', 'bg-sage' => $meter['level'] === 'ok', 'bg-amber-500' => $meter['level'] === 'high', 'bg-red-500' => $meter['level'] === 'full']) style="width: {{ $meter['percent'] }}%"></div>
                                </div>
                                @if ($meter['level'] === 'full')
                                    <p class="mt-1 text-xs font-medium text-red-600"><i class="fa-solid fa-circle-exclamation mr-1" aria-hidden="true"></i>{{ __('Limit reached') }}</p>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>

        {{-- Plans --}}
        <section aria-labelledby="plans-heading">
            <h2 id="plans-heading" class="mb-4 font-display text-2xl text-ink">{{ __('Plans') }}</h2>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($plans as ['plan' => $plan, 'is_current' => $isCurrent, 'is_lower' => $isLower])
                    <article @class(['flex flex-col rounded-xl border bg-white p-5', 'border-sage ring-2 ring-sage/30' => $plan->is_highlighted, 'border-line' => ! $plan->is_highlighted])>
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-semibold text-ink">{{ $plan->name }}</h3>
                            @if ($isCurrent)
                                <x-ui.badge variant="success">{{ __('Current') }}</x-ui.badge>
                            @elseif ($plan->is_highlighted)
                                <x-ui.badge variant="warning">{{ __('Popular') }}</x-ui.badge>
                            @endif
                        </div>
                        @if ($plan->description)
                            <p class="mt-1 text-sm text-muted">{{ $plan->description }}</p>
                        @endif

                        <ul class="mt-4 flex-1 space-y-1.5 text-sm">
                            @foreach ($plan->resolvedFeatures() as $feature => $value)
                                <li class="flex items-start gap-2 {{ $value === false ? 'text-muted' : 'text-ink-soft' }}">
                                    <i class="fa-solid {{ $value === false ? 'fa-xmark' : 'fa-check text-sage' }} mt-1 text-xs" aria-hidden="true"></i>
                                    <span>
                                        @if ($features[$feature]['type'] === 'limit')
                                            <span class="font-medium text-ink">{{ $value === null ? __('Unlimited') : Number::format($value) }}</span>
                                        @endif
                                        {{ __($features[$feature]['label']) }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <div class="mt-5 space-y-2">
                            @if ($plan->is_free)
                                <p class="text-center text-sm font-medium text-ink">{{ __('Free') }}</p>
                            @elseif ($isLower)
                                <p class="text-center text-xs text-muted">{{ __('Available after your current plan ends.') }}</p>
                            @else
                                @forelse ($plan->activePrices as $price)
                                    <form method="POST" action="{{ route('dashboard.subscription.checkout') }}">
                                        @csrf
                                        <input type="hidden" name="price" value="{{ $price->ulid }}">
                                        <button type="submit" @class(['flex w-full items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition', 'border-ink bg-ink text-white hover:bg-ink-soft' => $plan->is_highlighted, 'border-line text-ink hover:border-ink/40' => ! $plan->is_highlighted])>
                                            <span>
                                                <span class="block font-semibold">{{ \App\Support\Money::format($price->amount, $price->currency) }}</span>
                                                <span class="block text-xs opacity-75">{{ $price->periodLabel() }}</span>
                                            </span>
                                            <span class="text-xs font-medium">{{ $isCurrent ? __('Extend') : __('Choose') }} <i class="fa-solid fa-arrow-right ml-1" aria-hidden="true"></i></span>
                                        </button>
                                    </form>
                                @empty
                                    <p class="text-center text-xs text-muted">{{ __('Not available for purchase yet.') }}</p>
                                @endforelse
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        {{-- Orders --}}
        <x-ui.card :title="__('Order history')" padding="p-0">
            @if ($orders->isEmpty())
                <x-ui.empty-state icon="fa-solid fa-receipt" :title="__('No orders yet.')" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($orders as $order)
                        <li>
                            <a href="{{ route('dashboard.subscription.orders.show', $order) }}" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-paper/60">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium text-ink">{{ $order->plan->name }} <span class="font-normal text-muted">· {{ $order->number }}</span></p>
                                    <p class="text-xs text-muted">{{ $order->created_at->translatedFormat('d M Y, H:i') }} · {{ \App\Support\Money::format($order->amount, $order->currency) }}</p>
                                </div>
                                <x-ui.badge :variant="$order->status->badgeVariant()">{{ $order->status->label() }}</x-ui.badge>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>
</x-layouts.app>
