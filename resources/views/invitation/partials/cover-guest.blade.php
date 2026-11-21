{{--
    "Kepada Yth." and the guest's name, right above the cover's open button (classic, Botanical Cream and designs
    imported from HTML; Mocha Glass has designs.mocha-glass.partials.guest). A link without a guest names
    "our honored guest". It takes the button's side (App\Support\CoverButton::side). $class: the design's own class.
--}}
<p @class(['inv-cover-guest', 'inv-cover-guest--'.\App\Support\CoverButton::side($config['button_align'] ?? null, $config['alignment'] ?? 'center'), ...(filled($class ?? null) ? [$class] : [])])>
    <span class="inv-cover-guest__label">{{ __('Dear Sir/Madam') }}</span>
    <span class="inv-cover-guest__name" dir="auto">{{ $invitation->guestName ?: __('Our honored guest') }}</span>
</p>
