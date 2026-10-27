<?php

namespace Database\Factories;

use App\Enums\GuestMessageStatus;
use App\Models\GuestMessage;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GuestMessage>
 */
class GuestMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
            'name' => fake()->name(),
            'message' => fake()->sentence(12),
            'status' => GuestMessageStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => GuestMessageStatus::Approved]);
    }
}
