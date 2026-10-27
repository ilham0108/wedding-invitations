<?php

namespace Database\Factories;

use App\Enums\RsvpAttendance;
use App\Models\Invitation;
use App\Models\Rsvp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rsvp>
 */
class RsvpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
            'guest_id' => null,
            'name' => fake()->name(),
            'attendance' => RsvpAttendance::Attending,
            'number_of_guests' => fake()->numberBetween(1, 3),
            'message' => fake()->boolean(50) ? fake()->sentence() : null,
        ];
    }

    public function notAttending(): static
    {
        return $this->state(fn (array $attributes) => ['attendance' => RsvpAttendance::NotAttending, 'number_of_guests' => 0]);
    }
}
