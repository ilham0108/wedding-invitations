{{--
    "Kepada Yth." and the guest's name, right above the cover's open button (classic, Botanical Cream and designs
    imported from HTML; Mocha Glass has designs.mocha-glass.partials.guest). The cover fields turn it off
    ("guest_greeting") or replace its greeting ("guest_salutation") and the name of a link without a guest
    ("guest_fallback"). It takes the button's side (App\Support\CoverButton::side). $class: the design's own class.
--}}
@if (\App\Support\CoverGuest::shown($config))
    <p @class(['inv-cover-guest', 'inv-cover-guest--'.\App\Support\CoverButton::side($config['button_align'] ?? null, $config['alignment'] ?? 'center'), ...(filled($class ?? null) ? [$class] : [])])>
        <span class="inv-cover-guest__label">{{ \App\Support\CoverGuest::salutation($config) }}</span>
        <span class="inv-cover-guest__name" dir="auto">{{ \App\Support\CoverGuest::name($config, $invitation->guestName) }}</span>
    </p>
@endif
