<?php

namespace App\Livewire;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Livewire\Concerns\Notifies;
use App\Models\Plan;
use App\Services\Subscription\PlanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin: plans, their features (from config/plans.php), and their prices.
 */
class PlanManager extends Component
{
    use Notifies;

    public bool $showForm = false;

    #[Locked]
    public ?string $editingUlid = null;

    /** @var array{slug: string, name: string, description: string, is_active: bool, is_highlighted: bool, is_free: bool, rank: int, sort_order: int} */
    public array $form = [];

    /** @var array<string, array{value: string, unlimited: bool}|bool> */
    public array $features = [];

    /** @var array<int, array{ulid: ?string, billing_period: string, duration_days: ?int, amount: int|string, is_active: bool}> */
    public array $prices = [];

    public function mount(): void
    {
        Gate::authorize('plans.manage');
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingUlid = null;
        $this->form = ['slug' => '', 'name' => '', 'description' => '', 'is_active' => true, 'is_highlighted' => false, 'is_free' => false, 'rank' => (int) Plan::query()->max('rank') + 1, 'sort_order' => (int) Plan::query()->max('sort_order') + 1];
        $this->features = $this->featureState(null);
        $this->prices = [['ulid' => null, 'billing_period' => BillingPeriod::OneTime->value, 'duration_days' => 365, 'amount' => '', 'is_active' => true]];
        $this->showForm = true;
    }

    public function edit(string $planUlid): void
    {
        $plan = $this->plan($planUlid)->load('prices');

        $this->resetValidation();
        $this->editingUlid = $plan->ulid;
        $this->form = $plan->only(['slug', 'name', 'is_active', 'is_highlighted', 'is_free', 'rank', 'sort_order']) + ['description' => (string) $plan->description];
        $this->features = $this->featureState($plan);
        $this->prices = $plan->prices->map(fn ($price): array => [
            'ulid' => $price->ulid,
            'billing_period' => $price->billing_period->value,
            'duration_days' => $price->duration_days,
            'amount' => $price->amount,
            'is_active' => $price->is_active,
        ])->all();
        $this->showForm = true;
    }

    public function addPrice(): void
    {
        $this->prices[] = ['ulid' => null, 'billing_period' => BillingPeriod::Monthly->value, 'duration_days' => null, 'amount' => '', 'is_active' => true];
    }

    public function removePrice(int $index): void
    {
        unset($this->prices[$index]);
        $this->prices = array_values($this->prices);
    }

    public function save(PlanService $plans): void
    {
        Gate::authorize('plans.manage');

        $plan = $this->editingUlid ? $this->plan($this->editingUlid) : new Plan;

        $rules = [
            'form.slug' => ['required', 'string', 'max:60', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'form.name' => ['required', 'string', 'max:80'],
            'form.description' => ['nullable', 'string', 'max:255'],
            'form.rank' => ['required', 'integer', 'min:0', 'max:1000'],
            'form.sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];

        // The free plan has no prices; anything typed there is ignored, not validated.
        if (! $this->form['is_free']) {
            $rules += [
                'prices' => ['required', 'array', 'max:6'],
                'prices.*.billing_period' => ['required', Rule::enum(BillingPeriod::class)],
                'prices.*.duration_days' => ['nullable', 'required_if:prices.*.billing_period,one_time', 'integer', 'min:1', 'max:3650'],
                'prices.*.amount' => ['required', 'integer', 'min:1000', 'max:100000000'],
            ];
        }

        foreach (config('plans.features') as $key => $definition) {
            if ($definition['type'] === 'limit') {
                $rules["features.{$key}.value"] = ["required_unless:features.{$key}.unlimited,true", 'nullable', 'integer', 'min:0', 'max:1000000'];
            }
        }

        $this->validate($rules, [], $this->attributes());

        $plans->save($plan, [
            ...$this->form,
            'description' => trim($this->form['description']) ?: null,
            'rank' => (int) $this->form['rank'],
            'sort_order' => (int) $this->form['sort_order'],
        ], $this->featureValues(), $this->form['is_free'] ? [] : $this->prices, Auth::user());

        $this->showForm = false;
        $this->notify('success', __('Plan saved.'));
    }

    public function remove(string $planUlid, PlanService $plans): void
    {
        Gate::authorize('plans.manage');

        $plans->remove($this->plan($planUlid), Auth::user())
            ? $this->notify('success', __('Plan deleted.'))
            : $this->notify('warning', __('This plan has customers or is the free plan, so it was deactivated instead of deleted.'));
    }

    public function render(): View
    {
        return view('livewire.plan-manager', [
            'plans' => Plan::query()->ordered()->with('prices')->withCount(['subscriptions' => fn ($query) => $query->where('status', SubscriptionStatus::Active)])->get(),
            'catalog' => config('plans.features'),
            'periods' => BillingPeriod::cases(),
        ]);
    }

    /**
     * @return array<string, array{value: string, unlimited: bool}|bool>
     */
    private function featureState(?Plan $plan): array
    {
        $values = $plan?->resolvedFeatures() ?? array_map(fn (array $definition): mixed => $definition['default'], config('plans.features'));

        return collect(config('plans.features'))->mapWithKeys(fn (array $definition, string $key): array => [
            $key => $definition['type'] === 'boolean'
                ? (bool) $values[$key]
                : ['value' => $values[$key] === null ? '' : (string) $values[$key], 'unlimited' => $values[$key] === null],
        ])->all();
    }

    /**
     * @return array<string, int|bool|null>
     */
    private function featureValues(): array
    {
        return collect(config('plans.features'))->mapWithKeys(fn (array $definition, string $key): array => [
            $key => $definition['type'] === 'boolean'
                ? (bool) ($this->features[$key] ?? false)
                : (($this->features[$key]['unlimited'] ?? false) ? null : (int) ($this->features[$key]['value'] ?? 0)),
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        $attributes = ['form.slug' => 'slug', 'form.name' => mb_strtolower(__('Name')), 'prices.*.amount' => mb_strtolower(__('Price')), 'prices.*.duration_days' => mb_strtolower(__('Days'))];

        foreach (config('plans.features') as $key => $definition) {
            $attributes["features.{$key}.value"] = mb_strtolower(__($definition['label']));
        }

        return $attributes;
    }

    private function plan(string $planUlid): Plan
    {
        return Plan::query()->where('ulid', $planUlid)->firstOrFail();
    }
}
