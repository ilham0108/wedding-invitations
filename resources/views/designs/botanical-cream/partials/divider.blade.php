{{--
    A divider in Botanical Cream: the design's own mark ($mark: "sprig" | "diamond"), the decoration's
    library ornament (divider "image", drawn by invitation.css from --inv-divider-image), or nothing.
--}}
@php($divider = $invitation->decoration['divider'] ?? 'line')
@if ($divider === 'image')
    <span class="inv-divider-image {{ $class ?? '' }}" aria-hidden="true"></span>
@elseif ($divider !== 'none' && $mark === 'sprig')
    @include('designs.botanical-cream.partials.sprig', isset($class) ? ['class' => $class] : [])
@elseif ($divider !== 'none')
    @include('designs.botanical-cream.partials.diamond')
@endif
