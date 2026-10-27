<?php

namespace Database\Factories;

use App\Enums\WhatsAppDriver;
use App\Models\WhatsAppProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppProvider>
 */
class WhatsAppProviderFactory extends Factory
{
    protected $model = WhatsAppProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver' => WhatsAppDriver::Log,
            'name' => 'Development log',
            'credentials' => [],
            'rate_per_minute' => 600,
            'is_active' => true,
        ];
    }

    public function fonnte(): static
    {
        return $this->state(fn (array $attributes) => [
            'driver' => WhatsAppDriver::Fonnte,
            'name' => 'Fonnte',
            'credentials' => ['token' => 'fonnte-secret-token'],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
