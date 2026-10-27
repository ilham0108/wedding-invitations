@props(['name', 'options' => [], 'value' => null])

<select
    name="{{ $name }}"
    id="{{ $attributes->get('id', $name) }}"
    {{ $attributes->except('id')->class('block w-full rounded-lg border-line bg-white text-sm text-ink shadow-sm transition focus:border-sage focus:ring-sage') }}
>
    @foreach ($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) old($name, $value) === (string) $optionValue)>{{ $optionLabel }}</option>
    @endforeach
</select>
