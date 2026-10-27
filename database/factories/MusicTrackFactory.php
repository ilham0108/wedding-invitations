<?php

namespace Database\Factories;

use App\Models\MusicTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MusicTrack>
 */
class MusicTrackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'artist' => fake()->name(),
            'disk' => 'public',
            'path' => 'music/'.fake()->unique()->lexify('????????').'.mp3',
            'size' => 1024,
            'is_premium' => false,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => ['is_premium' => true]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
