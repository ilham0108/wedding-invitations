<?php

namespace Database\Factories;

use App\Enums\BillingPeriod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'INV-TEST-'.Str::upper(Str::random(8)),
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'billing_period' => BillingPeriod::OneTime,
            'duration_days' => 365,
            'amount' => 149000,
            'currency' => 'IDR',
            'status' => OrderStatus::Pending,
            'expires_at' => now()->addDay(),
        ];
    }
}
