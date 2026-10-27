<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Enums\InvitationStatus;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates the invitation row only; use InvitationService::createFromTemplate() when sections are needed.
 *
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->firstName().' & '.fake()->firstName();

        return [
            'user_id' => User::factory(),
            'invitation_template_id' => InvitationTemplate::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'status' => InvitationStatus::Draft,
            'event_type' => EventType::Wedding,
            'timezone' => 'Asia/Jakarta',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvitationStatus::Published,
            'published_at' => now()->subMonth(),
            'expires_at' => now()->subDay(),
        ]);
    }
}
