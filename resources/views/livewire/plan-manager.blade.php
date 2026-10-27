<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-muted">{{ __('Plans, limits, and prices come from here. Existing subscribers keep the limits they paid for; changes apply to new purchases.') }}</p>
        <x-ui.button type="button" icon="fa-solid fa-plus" wire:click="create">{{ __('New plan') }}</x-ui.button>
    </div>


    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @forelse ($plans as $plan)
            <article wire:key="plan-{{ $plan->ulid }}" @class(['flex flex-col rounded-xl border bg-white p-5', 'border-line' => $plan->is_active, 'border-dashed border-line opacity-60' => ! $plan->is_active])>
                <div class="flex items-center justify-between gap-2">
                    <h3 class="font-semibold text-ink">{{ $plan->name }}</h3>
                    <div class="flex gap-1">
                        @if ($plan->is_free)<x-ui.badge>{{ __('Free') }}</x-ui.badge>@endif
                        @if ($plan->is_highlighted)<x-ui.badge variant="warning">{{ __('Popular') }}</x-ui.badge>@endif
                        @unless ($plan->is_active)<x-ui.badge>{{ __('Hidden') }}</x-ui.badge>@endunless
                    </div>
                </div>
                <p class="text-xs text-muted">{{ $plan->slug }} · {{ __('rank :rank', ['rank' => $plan->rank]) }} · {{ trans_choice(':count active subscriber|:count active subscribers', $plan->subscriptions_count) }}</p>

                <ul class="mt-3 flex-1 space-y-1 text-xs text-ink-soft">
                    @foreach ($plan->resolvedFeatures() as $feature => $value)
                        <li>
                            {{ __($catalog[$feature]['label']) }}:
                            <span class="font-medium text-ink">{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : ($value === null ? __('Unlimited') : Number::format($value)) }}</span>
                        </li>
                    @endforeach
                </ul>

                <ul class="mt-3 space-y-1 border-t border-line pt-3 text-sm">
                    @forelse ($plan->prices as $price)
                        <li @class(['flex justify-between', 'text-muted line-through' => ! $price->is_active])>
                            <span>{{ $price->periodLabel() }}</span>
                            <span class="font-medium">{{ \App\Support\Money::format($price->amount, $price->currency) }}</span>
                        </li>
                    @empty
                        <li class="text-xs text-muted">{{ __('No prices') }}</li>
                    @endforelse
                </ul>

                <div class="mt-4 flex justify-end gap-1">
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="edit('{{ $plan->ulid }}')">{{ __('Edit') }}</x-ui.button>
                    <button type="button" wire:click="remove('{{ $plan->ulid }}')" data-confirm="{{ __('Delete this plan?') }}" class="rounded p-1.5 text-muted hover:bg-red-50 hover:text-red-600" aria-label="{{ __('Delete') }}"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>
                </div>
            </article>
        @empty
            <x-ui.card padding="p-0" class="md:col-span-2 xl:col-span-4">
                <x-ui.empty-state icon="fa-regular fa-gem" :title="__('No plans yet.')" />
            </x-ui.card>
        @endforelse
    </div>

    @if ($showForm)
        @teleport('body')
        <div class="fixed inset-0 z-[60] flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" x-data x-on:keydown.escape.window="$wire.set('showForm', false)">
            <div class="fixed inset-0 bg-ink/40" wire:click="$set('showForm', false)"></div>
            <form wire:submit="save" class="relative max-h-[90vh] w-full max-w-2xl space-y-5 overflow-y-auto rounded-xl bg-white p-6 shadow-soft">
                <h2 class="text-lg font-semibold text-ink">{{ $editingUlid ? __('Edit plan') : __('New plan') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="form.name" for="plan_name" :label="__('Name')">
                        <input id="plan_name" type="text" wire:model="form.name" maxlength="80" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                    <x-ui.field name="form.slug" for="plan_slug" label="Slug" :hint="__('Lowercase, used in links and reports.')">
                        <input id="plan_slug" type="text" wire:model="form.slug" maxlength="60" class="block w-full rounded-lg border-line font-mono text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>
                <x-ui.field name="form.description" for="plan_description" :label="__('Description')">
                    <input id="plan_description" type="text" wire:model="form.description" maxlength="255" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                </x-ui.field>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.field name="form.rank" for="plan_rank" :label="__('Rank')" :hint="__('Higher = more generous. Customers cannot buy a lower plan while a higher one runs.')">
                        <input id="plan_rank" type="number" min="0" wire:model="form.rank" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                    <x-ui.field name="form.sort_order" for="plan_sort" :label="__('Display order')">
                        <input id="plan_sort" type="number" min="0" wire:model="form.sort_order" class="block w-full rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                    </x-ui.field>
                </div>
                <div class="flex flex-wrap gap-4 text-sm text-ink-soft">
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_active" class="rounded border-line text-sage focus:ring-sage"> {{ __('Visible') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.is_highlighted" class="rounded border-line text-sage focus:ring-sage"> {{ __('Popular') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" wire:model.live="form.is_free" class="rounded border-line text-sage focus:ring-sage"> {{ __('Free plan (everyone without a paid plan)') }}</label>
                </div>

                <fieldset class="space-y-3 rounded-lg border border-line p-4">
                    <legend class="px-1 text-sm font-medium text-ink">{{ __('Limits & features') }}</legend>
                    @foreach ($catalog as $key => $definition)
                        @if ($definition['type'] === 'limit')
                            <div class="grid grid-cols-[1fr_8rem_auto] items-center gap-3 text-sm">
                                <label for="feature_{{ $key }}" class="text-ink-soft">{{ __($definition['label']) }}</label>
                                <input id="feature_{{ $key }}" type="number" min="0" wire:model="features.{{ $key }}.value" @disabled($features[$key]['unlimited'] ?? false)
                                    class="h-8 rounded-lg border-line text-sm focus:border-sage focus:ring-sage disabled:bg-paper">
                                <label class="flex items-center gap-1 text-xs text-muted"><input type="checkbox" wire:model.live="features.{{ $key }}.unlimited" class="rounded border-line text-sage focus:ring-sage"> {{ __('Unlimited') }}</label>
                            </div>
                            @error("features.{$key}.value")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        @else
                            <label class="flex items-center gap-2 text-sm text-ink-soft"><input type="checkbox" wire:model="features.{{ $key }}" class="rounded border-line text-sage focus:ring-sage"> {{ __($definition['label']) }}</label>
                        @endif
                    @endforeach
                </fieldset>

                @unless ($form['is_free'])
                    <fieldset class="space-y-3 rounded-lg border border-line p-4">
                        <legend class="px-1 text-sm font-medium text-ink">{{ __('Prices') }}</legend>
                        @foreach ($prices as $index => $price)
                            <div wire:key="price-{{ $index }}" class="grid grid-cols-2 items-end gap-3 sm:grid-cols-[9rem_6rem_1fr_auto_auto]">
                                <select wire:model.live="prices.{{ $index }}.billing_period" aria-label="{{ __('Billing') }}" class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                                    @foreach ($periods as $period)
                                        <option value="{{ $period->value }}">{{ $period->label() }}</option>
                                    @endforeach
                                </select>
                                <input type="number" min="1" wire:model="prices.{{ $index }}.duration_days" placeholder="{{ __('Days') }}" aria-label="{{ __('Days') }}" @disabled($price['billing_period'] !== 'one_time')
                                    class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage disabled:bg-paper">
                                <input type="number" min="0" step="1000" wire:model="prices.{{ $index }}.amount" placeholder="149000" aria-label="{{ __('Price (Rp)') }}"
                                    class="h-9 rounded-lg border-line text-sm focus:border-sage focus:ring-sage">
                                <label class="flex items-center gap-1 text-xs text-muted"><input type="checkbox" wire:model="prices.{{ $index }}.is_active" class="rounded border-line text-sage focus:ring-sage"> {{ __('On sale') }}</label>
                                <button type="button" wire:click="removePrice({{ $index }})" class="text-xs text-red-600 hover:underline">{{ __('Remove') }}</button>
                            </div>
                            @error("prices.{$index}.amount")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                            @error("prices.{$index}.duration_days")<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        @endforeach
                        @error('prices')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        <button type="button" wire:click="addPrice" class="text-xs font-medium text-sage hover:text-ink"><i class="fa-solid fa-plus" aria-hidden="true"></i> {{ __('Add price') }}</button>
                    </fieldset>
                @endunless

                <div class="flex justify-end gap-2">
                    <x-ui.button type="button" variant="secondary" wire:click="$set('showForm', false)">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button wire:loading.attr="disabled" wire:target="save">{{ __('Save plan') }}</x-ui.button>
                </div>
            </form>
        </div>
        @endteleport
    @endif
</div>
