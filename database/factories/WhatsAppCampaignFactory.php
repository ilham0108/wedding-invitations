<?php

namespace Database\Factories;

use App\Enums\WhatsAppCampaignStatus;
use App\Models\Invitation;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppCampaign>
 */
class WhatsAppCampaignFactory extends Factory
{
    protected $model = WhatsAppCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'invitation_id' => Invitation::factory(),
            'name' => 'Invitation blast',
            'message' => 'Dear {{guest_name}}, see {{invitation_url}}',
            'status' => WhatsAppCampaignStatus::Draft,
            'timezone' => 'Asia/Jakarta',
            'total_recipients' => 0,
        ];
    }
}
