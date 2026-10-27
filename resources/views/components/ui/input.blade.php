@props(['name', 'type' => 'text', 'value' => null])

<input
    type="{{ $type }}"
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
    @error($name, $attributes->get('bag', 'default')) aria-invalid="true" @enderror
    {{ $attributes->except(['id', 'bag'])->class([
        'block w-full rounded-lg border-line bg-white text-sm text-ink placeholder:text-muted/70 shadow-sm transition focus:border-sage focus:ring-sage',
        'border-red-400 focus:border-red-500 focus:ring-red-500' => $errors->getBag($attributes->get('bag', 'default'))->has($name),
    ]) }}
>
