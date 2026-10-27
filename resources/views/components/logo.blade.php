@if ($src)
    <img src="{{ $src }}" alt="{{ $name }}" {{ $attributes->class(['w-auto', $height]) }}>
@else
    <span {{ $attributes->class(['font-display text-ink', $textClass]) }}>{{ $name }}</span>
@endif
