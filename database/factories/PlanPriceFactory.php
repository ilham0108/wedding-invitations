<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'billing_period' => BillingPeriod::OneTime,
            'duration_days' => 365,
            'amount' => 149000,
            'currency' => 'IDR',
            'is_active' => true,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => ['billing_period' => BillingPeriod::Monthly, 'duration_days' => null]);
    }
}
