{{--
    "Kepada Yth." and the guest's name, with the design's line beside it. On the cover layer it sits right above the
    open button. $config: the cover's configuration (App\Support\CoverGuest reads its guest fields).
--}}
@if (\App\Support\CoverGuest::shown($config))
    <div class="mg-guest">
        <div>{{ \App\Support\CoverGuest::salutation($config) }}<span dir="auto">{{ \App\Support\CoverGuest::name($config, $invitation->guestName) }}</span></div>
    </div>
@endif
