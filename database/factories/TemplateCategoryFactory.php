<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TemplateCategory>
 */
class TemplateCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            // Seeded categories use the event type as slug; mirror that so tests can match by slug.
            'event_type' => fn (array $attributes): EventType => EventType::tryFrom($attributes['slug']) ?? EventType::Other,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
