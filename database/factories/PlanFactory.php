<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' Plan';

        return [
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'name' => $name,
            'description' => null,
            'features' => [],
            'is_free' => false,
            'is_active' => true,
            'is_highlighted' => false,
            'rank' => 10,
            'sort_order' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => ['is_free' => true, 'rank' => 0]);
    }

    /**
     * @param  array<string, int|bool|null>  $features
     */
    public function features(array $features): static
    {
        return $this->state(fn (array $attributes) => ['features' => $features]);
    }
}
