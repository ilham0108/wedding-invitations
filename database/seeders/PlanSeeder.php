<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\Plan;
use App\Models\User;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Database\Seeder;

/**
 * Four starter plans (rules.md section 72). Created only when missing, so prices and
 * limits edited by the admin are never overwritten by re-seeding.
 */
class PlanSeeder extends Seeder
{
    public function run(SubscriptionService $subscriptions): void
    {
        foreach ($this->plans() as $sortOrder => $definition) {
            $plan = Plan::query()->firstOrCreate(['slug' => $definition['slug']], [
                ...$definition['plan'],
                'sort_order' => $sortOrder,
            ]);

            if ($plan->wasRecentlyCreated) {
                foreach ($definition['prices'] as $price) {
                    $plan->prices()->create([...$price, 'currency' => 'IDR', 'is_active' => true]);
                }
            }
        }

        // The demo account showcases several invitations, so it gets Premium.
        $demo = User::query()->where('email', config('seeding.demo.email'))->first();
        $premium = Plan::query()->where('slug', 'premium')->first();

        if ($demo !== null && $premium !== null && ! $demo->subscriptions()->current()->exists()) {
            $subscriptions->grant($demo, $premium, 365);
        }
    }

    /**
     * @return array<int, array{slug: string, plan: array<string, mixed>, prices: array<int, array<string, mixed>>}>
     */
    private function plans(): array
    {
        return [
            [
                'slug' => 'free',
                'plan' => [
                    'name' => 'Gratis',
                    'description' => 'Untuk mencoba dan acara kecil.',
                    'is_free' => true,
                    'rank' => 0,
                    'features' => [
                        'max_invitations' => 1, 'max_guests' => 50, 'max_whatsapp_messages' => 50,
                        'premium_templates' => false, 'premium_illustrations' => false, 'premium_music' => false, 'custom_music' => false,
                        'remove_branding' => false, 'theme_customization' => false, 'analytics' => false, 'custom_domain' => false,
                    ],
                ],
                'prices' => [],
            ],
            [
                'slug' => 'basic',
                'plan' => [
                    'name' => 'Basic',
                    'description' => 'Satu acara dengan tamu lebih banyak, tanpa logo kami.',
                    'rank' => 10,
                    'features' => [
                        'max_invitations' => 1, 'max_guests' => 300, 'max_whatsapp_messages' => 500,
                        'premium_templates' => false, 'premium_illustrations' => false, 'premium_music' => false, 'custom_music' => false,
                        'remove_branding' => true, 'theme_customization' => false, 'analytics' => false, 'custom_domain' => false,
                    ],
                ],
                'prices' => [
                    ['billing_period' => BillingPeriod::OneTime, 'duration_days' => 365, 'amount' => 79000],
                ],
            ],
            [
                'slug' => 'premium',
                'plan' => [
                    'name' => 'Premium',
                    'description' => 'Semua template dan ilustrasi premium untuk acara spesial.',
                    'rank' => 20,
                    'is_highlighted' => true,
                    'features' => [
                        'max_invitations' => 3, 'max_guests' => 1000, 'max_whatsapp_messages' => 2000,
                        'premium_templates' => true, 'premium_illustrations' => true, 'premium_music' => true, 'custom_music' => true,
                        'remove_branding' => true, 'theme_customization' => false, 'analytics' => true, 'custom_domain' => false,
                    ],
                ],
                'prices' => [
                    ['billing_period' => BillingPeriod::OneTime, 'duration_days' => 365, 'amount' => 149000],
                ],
            ],
            [
                'slug' => 'business',
                'plan' => [
                    'name' => 'Business',
                    'description' => 'Untuk wedding organizer dan event organizer.',
                    'rank' => 30,
                    'features' => [
                        'max_invitations' => 50, 'max_guests' => 5000, 'max_whatsapp_messages' => 10000,
                        'premium_templates' => true, 'premium_illustrations' => true, 'premium_music' => true, 'custom_music' => true,
                        'remove_branding' => true, 'theme_customization' => true, 'analytics' => true, 'custom_domain' => true,
                    ],
                ],
                'prices' => [
                    ['billing_period' => BillingPeriod::Monthly, 'duration_days' => null, 'amount' => 299000],
                    ['billing_period' => BillingPeriod::Yearly, 'duration_days' => null, 'amount' => 2990000],
                ],
            ],
        ];
    }
}
