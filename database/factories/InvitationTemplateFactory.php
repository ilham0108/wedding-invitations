<?php

namespace Database\Factories;

use App\Enums\TemplateStatus;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvitationTemplate>
 */
class InvitationTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'template_category_id' => TemplateCategory::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'schema_version' => 1,
            'design' => 'classic',
            'schema' => ['sections' => ['cover', 'event', 'countdown', 'closing']],
            'default_configuration' => [],
            'theme' => ['preset' => 'elegant'],
            'decoration' => [],
            'sample_content' => ['cover' => ['title' => 'Sample Title']],
            'is_premium' => false,
            'status' => TemplateStatus::Published,
            'sort_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TemplateStatus::Draft]);
    }
}
