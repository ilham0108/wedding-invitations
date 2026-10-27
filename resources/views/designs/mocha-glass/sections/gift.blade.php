<div x-data="{ modal: null }">
    <div class="mg-card mg-center inv-animate">
        <h2 class="mg-title">{{ $config['heading'] }}</h2>
        @if (filled($config['description']))
            <p class="mg-lead">{{ $config['description'] }}</p>
        @endif
        @if ($config['accounts'] || filled($config['address']))
            <button type="button" class="mg-btn-white mg-btn-white--tall" x-on:click="modal = 'gift'">
                <i class="fa-solid fa-gift" aria-hidden="true"></i>{{ __('Send a gift') }}
            </button>
        @endif
    </div>

    @if ($config['wishlist'])
        <div class="mg-dots inv-animate" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="mg-center inv-animate">
            <h2 class="mg-title">{{ $config['wishlist_heading'] }}</h2>
            <button type="button" class="mg-btn-white mg-btn-white--tall mg-narrow" x-on:click="modal = 'wishlist'">
                <i class="fa-solid fa-list-check" aria-hidden="true"></i>{{ __('See the gift registry') }}
            </button>
        </div>
    @endif

    <template x-teleport="body">
        <div class="mg-modal" x-show="modal" x-cloak x-transition.opacity x-on:click.self="modal = null" x-on:keydown.escape.window="modal = null" role="dialog" aria-modal="true">
            <div class="mg-modal__box">
                <button type="button" class="mg-modal__close" x-on:click="modal = null" aria-label="{{ __('Close') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>

                <div x-show="modal === 'gift'">
                    <h3 class="mg-modal__title">{{ __('Send a gift') }}</h3>
                    @foreach ($config['accounts'] as $account)
                        <div class="mg-bank" x-data="copyText(@js((string) ($account['account_number'] ?? '')))">
                            <p class="mg-bank__type">{{ ($account['type'] ?? 'bank') === 'ewallet' ? __('E-wallet') : __('Bank') }}</p>
                            <b>{{ $account['provider'] ?? '' }}</b>
                            <p class="mg-bank__number">{{ $account['account_number'] ?? '' }}</p>
                            <p>{{ __('a.n. :name', ['name' => $account['account_name'] ?? '']) }}</p>
                            <button type="button" x-on:click="copy()"><span x-text="copied ? @js(__('Copied!')) : @js(__('Copy number'))">{{ __('Copy number') }}</span></button>
                        </div>
                    @endforeach
                    @if (filled($config['address']))
                        <div class="mg-bank" x-data="copyText(@js((string) $config['address']))">
                            <b>{{ __('Gift address') }}</b>
                            <p>{{ $config['address'] }}</p>
                            <button type="button" x-on:click="copy()"><span x-text="copied ? @js(__('Copied!')) : @js(__('Copy address'))">{{ __('Copy address') }}</span></button>
                        </div>
                    @endif
                </div>

                <div x-show="modal === 'wishlist'">
                    <h3 class="mg-modal__title">{{ $config['wishlist_heading'] }}</h3>
                    <ul>
                        @foreach ($config['wishlist'] as $item)
                            <li class="mg-wishlist-item">
                                <span>{{ $item['item'] ?? '' }}</span>
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">{{ __('View') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </template>
</div>
