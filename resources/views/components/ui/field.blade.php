@props(['name', 'label', 'hint' => null, 'bag' => 'default', 'for' => null])

<div {{ $attributes->class('space-y-1.5') }}>
    <label for="{{ $for ?? $name }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    {{ $slot }}
    @if ($hint)
        <p class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @error($name, $bag)
        <p class="text-xs font-medium text-red-600">{{ $message }}</p>
    @enderror
</div>
