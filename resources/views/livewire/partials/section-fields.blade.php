{{--
    A section's editor fields, in the groups of config('invitation.field_groups') (field key "group", else "content").
    With one group the fields are listed as they are. With more, each group is a collapsible part; the first one
    starts open. The open state lives in Alpine here, and wire:ignore.self keeps Livewire updates from closing a part
    again. Groups whose fields do not apply right now (App\Support\FieldVisibility) are left out. Toggles that follow
    each other sit side by side.
--}}
@php
    $groupDefinitions = config('invitation.field_groups');
    $fieldGroups = collect($fields)
        ->filter(fn (array $field): bool => \App\Support\FieldVisibility::shown($field, $config, $field['key'], $variant ?? null, $designFeatures ?? []))
        ->groupBy(fn (array $field): string => isset($groupDefinitions[$field['group'] ?? 'content']) ? ($field['group'] ?? 'content') : 'content');

    // Runs of toggles become one row of a two-column grid; any other field is its own row.
    $rows = fn ($groupFields): array => $groupFields->values()->reduce(function (array $rows, array $field): array {
        $last = array_key_last($rows);

        if ($field['type'] === 'toggle' && $last !== null && $rows[$last][0]['type'] === 'toggle') {
            $rows[$last][] = $field;
        } else {
            $rows[] = [$field];
        }

        return $rows;
    }, []);
@endphp

@if ($fieldGroups->count() <= 1)
    @foreach ($rows($fieldGroups->first() ?? collect()) as $row)
        @if (count($row) > 1)
            <div class="grid gap-x-4 gap-y-3 sm:grid-cols-2">
                @foreach ($row as $field)
                    @include('livewire.partials.section-field', ['field' => $field, 'path' => $field['key']])
                @endforeach
            </div>
        @else
            @include('livewire.partials.section-field', ['field' => $row[0], 'path' => $row[0]['key']])
        @endif
    @endforeach
@else
    <div class="divide-y divide-line overflow-hidden rounded-xl border border-line" x-data="{ open: { '{{ $fieldGroups->keys()->first() }}': true }, toggle(id) { this.open[id] = ! this.open[id] } }">
        @foreach ($fieldGroups as $groupKey => $groupFields)
            <section wire:key="field-group-{{ $groupKey }}">
                <h3>
                    <button type="button" wire:ignore.self x-on:click="toggle('{{ $groupKey }}')" x-bind:aria-expanded="open['{{ $groupKey }}'] ? 'true' : 'false'" aria-controls="field-group-{{ $groupKey }}"
                        class="flex min-h-11 w-full cursor-pointer items-center gap-2.5 px-3.5 py-2.5 text-left text-sm font-medium text-ink transition-colors hover:bg-paper active:bg-sage-soft focus-visible:outline focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-sage">
                        <i class="{{ $groupDefinitions[$groupKey]['icon'] }} w-4 shrink-0 text-center text-sage" aria-hidden="true"></i>
                        <span class="min-w-0 flex-1">{{ __($groupDefinitions[$groupKey]['label']) }}</span>
                        <i wire:ignore.self class="fa-solid fa-chevron-down shrink-0 text-xs text-ink-soft transition-transform motion-reduce:transition-none" x-bind:class="! open['{{ $groupKey }}'] && '-rotate-90'" aria-hidden="true"></i>
                    </button>
                </h3>
                <div id="field-group-{{ $groupKey }}" wire:ignore.self x-show="open['{{ $groupKey }}']" x-cloak class="space-y-5 border-t border-line bg-white px-3.5 py-4">
                    @foreach ($rows($groupFields) as $row)
                        @if (count($row) > 1)
                            <div class="grid gap-x-4 gap-y-3 sm:grid-cols-2">
                                @foreach ($row as $field)
                                    @include('livewire.partials.section-field', ['field' => $field, 'path' => $field['key']])
                                @endforeach
                            </div>
                        @else
                            @include('livewire.partials.section-field', ['field' => $row[0], 'path' => $row[0]['key']])
                        @endif
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif
