<?php

namespace Database\Factories;

use App\Models\GuestGroup;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestGroup>
 */
class GuestGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
            'name' => fake()->unique()->randomElement(['Family', 'Friends', 'Office', 'VIP', 'Vendor', 'Neighbors', 'School']),
            'sort_order' => 0,
        ];
    }
}
