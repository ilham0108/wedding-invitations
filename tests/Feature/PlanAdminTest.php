<?php

namespace Tests\Feature;

use App\Livewire\PlanManager;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class PlanAdminTest extends TestCase
{
    use InteractsWithPlans, RefreshDatabase;

    public function test_only_admins_manage_plans_orders_and_payments(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $client = User::factory()->client()->create();

        foreach (['/admin/plans', '/admin/orders', '/admin/payments'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
            $this->actingAs($client)->get($url)->assertForbidden();
        }

        Livewire::actingAs($client)->test(PlanManager::class)->assertForbidden();
    }

    public function test_admin_creates_a_plan_with_features_and_prices(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(PlanManager::class)
            ->call('create')
            ->set('form.name', 'Gold')
            ->set('form.slug', 'gold')
            ->set('features.max_invitations.value', '5')
            ->set('features.max_guests.unlimited', true)
            ->set('features.premium_templates', true)
            ->set('prices.0.amount', 199000)
            ->set('prices.0.duration_days', 180)
            ->call('addPrice')
            ->set('prices.1.amount', 49000)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Gold');

        $plan = Plan::query()->where('slug', 'gold')->sole();

        $this->assertSame(5, $plan->features['max_invitations']);
        $this->assertNull($plan->features['max_guests']);
        $this->assertTrue($plan->features['premium_templates']);
        $this->assertEqualsCanonicalizing([49000, 199000], $plan->prices()->pluck('amount')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'plan.created']);
    }

    public function test_plan_validation_and_single_free_plan(): void
    {
        $oldFree = $this->freePlan();

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(PlanManager::class)
            ->call('create')
            ->set('form.name', 'Bad')
            ->set('form.slug', 'Not a slug!')
            ->set('prices.0.amount', 5)
            ->call('save')
            ->assertHasErrors(['form.slug', 'prices.0.amount'])
            ->set('form.slug', 'new-free')
            ->set('form.is_free', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($oldFree->fresh()->is_free);
        $this->assertTrue(Plan::query()->where('slug', 'new-free')->sole()->is_free);
    }

    public function test_plans_with_customers_and_ordered_prices_are_kept(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $plan = Plan::factory()->create(['name' => 'Kept']);
        $ordered = PlanPrice::factory()->for($plan)->create();
        Order::factory()->for($plan)->create(['plan_price_id' => $ordered->id]);

        Livewire::actingAs($admin)
            ->test(PlanManager::class)
            ->call('edit', $plan->ulid)
            ->call('removePrice', 0)
            ->call('addPrice')
            ->set('prices.0.amount', 99000)
            ->call('save')
            ->assertHasNoErrors()
            ->call('remove', $plan->ulid)
            ->assertDispatched('notify', type: 'warning', message: 'This plan has customers or is the free plan, so it was deactivated instead of deleted.');

        $this->assertFalse($ordered->fresh()->is_active, 'An ordered price is retired, not deleted.');
        $this->assertFalse($plan->fresh()->is_active);
    }

    public function test_landing_page_prices_come_from_the_database(): void
    {
        $plan = Plan::factory()->create(['name' => 'Wedding Pro']);
        PlanPrice::factory()->for($plan)->create(['amount' => 123000]);

        $this->get('/')->assertOk()->assertSee('Wedding Pro')->assertSee('Rp 123.000');
    }
}
