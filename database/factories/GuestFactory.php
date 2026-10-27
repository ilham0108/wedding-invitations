<?php

namespace Database\Factories;

use App\Enums\GuestInvitationStatus;
use App\Enums\RsvpStatus;
use App\Models\Guest;
use App\Models\Invitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invitation_id' => Invitation::factory(),
            'name' => fake()->name(),
            'phone' => '628'.fake()->unique()->numerify('##########'),
            'email' => fake()->boolean(40) ? fake()->unique()->safeEmail() : null,
            'number_of_guests' => fake()->numberBetween(1, 4),
            'rsvp_status' => RsvpStatus::Pending,
            'invitation_status' => GuestInvitationStatus::NotSent,
        ];
    }

    public function attending(): static
    {
        return $this->state(fn (array $attributes) => ['rsvp_status' => RsvpStatus::Attending]);
    }

    public function notAttending(): static
    {
        return $this->state(fn (array $attributes) => ['rsvp_status' => RsvpStatus::NotAttending]);
    }
}
