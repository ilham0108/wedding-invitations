<div class="inv-container text-center">
    <h2 class="inv-heading text-4xl">{{ $config['heading'] }}</h2>
    @if (filled($config['description']))
        <p class="mx-auto mt-4 max-w-md inv-muted">{{ $config['description'] }}</p>
    @endif

    <div class="mx-auto mt-10 grid max-w-2xl gap-4 sm:grid-cols-2">
        @foreach ($config['accounts'] as $account)
            <div class="inv-card p-6 text-left" x-data="copyText(@js((string) ($account['account_number'] ?? '')))">
                <p class="text-xs uppercase tracking-[0.25em] inv-accent">{{ ($account['type'] ?? 'bank') === 'ewallet' ? __('E-wallet') : __('Bank') }}</p>
                <p class="mt-1 font-semibold">{{ $account['provider'] ?? '' }}</p>
                <p class="mt-3 font-mono text-lg tracking-wider">{{ $account['account_number'] ?? '' }}</p>
                <p class="text-sm inv-muted">{{ $account['account_name'] ?? '' }}</p>
                <button type="button" class="inv-btn mt-5" x-on:click="copy()">
                    <i class="fa-regular fa-copy" aria-hidden="true"></i>
                    <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy number'))">{{ __('Copy number') }}</span>
                </button>
            </div>
        @endforeach
    </div>

    @if (filled($config['address']))
        <div class="inv-card mx-auto mt-4 max-w-2xl p-6" x-data="copyText(@js((string) $config['address']))">
            <p class="text-xs uppercase tracking-[0.25em] inv-accent">{{ __('Gift address') }}</p>
            <p class="mt-2 text-sm">{{ $config['address'] }}</p>
            <button type="button" class="inv-btn mt-4" x-on:click="copy()">
                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                <span x-text="copied ? @js(__('Copied!')) : @js(__('Copy address'))">{{ __('Copy address') }}</span>
            </button>
        </div>
    @endif

    @if (! empty($config['wishlist']))
        <h3 class="inv-heading mt-12 text-2xl">{{ $config['wishlist_heading'] }}</h3>
        <ul class="inv-card mx-auto mt-6 max-w-2xl divide-y divide-black/10 text-left">
            @foreach ($config['wishlist'] as $item)
                <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                    <span>{{ $item['item'] ?? '' }}</span>
                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" class="inv-btn shrink-0 !px-3 !py-1 text-xs">{{ __('View') }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
