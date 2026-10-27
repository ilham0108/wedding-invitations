<?php

namespace Database\Factories;

use App\Enums\IllustrationCategory;
use App\Models\Illustration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Illustration>
 */
class IllustrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category' => IllustrationCategory::Person,
            'name' => fake()->words(2, true),
            'disk' => null,
            'path' => 'illustrations/person-bride.svg',
            'width' => 600,
            'height' => 600,
            'is_premium' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function category(IllustrationCategory $category): static
    {
        return $this->state(fn (array $attributes) => ['category' => $category]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
