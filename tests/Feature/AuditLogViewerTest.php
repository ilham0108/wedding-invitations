<?php

namespace Tests\Feature;

use App\Livewire\AuditLogViewer;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_open_the_audit_log(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.audit-logs.index'))->assertOk()->assertSeeLivewire(AuditLogViewer::class);
        $this->actingAs(User::factory()->client()->create())->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_entries_are_filtered_by_type_search_and_date(): void
    {
        $ana = User::factory()->client()->create(['email' => 'ana@example.test']);
        $budi = User::factory()->client()->create(['email' => 'budi@example.test']);
        $logger = app(AuditLogger::class);

        $logger->record('template.published', $ana, null, ['slug' => 'garden']);
        $logger->record('guest.imported', $budi);
        AuditLog::query()->where('action', 'guest.imported')->update(['created_at' => now()->subDays(10)]);

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(AuditLogViewer::class)
            ->assertSee('template.published')
            ->assertSee('guest.imported')
            ->set('group', 'template')
            ->assertSee('template.published')
            ->assertDontSee('guest.imported')
            ->set('group', '')
            ->set('search', 'budi@')
            ->assertSee('guest.imported')
            ->assertDontSee('template.published')
            ->set('search', '')
            ->set('from', now()->subDays(2)->toDateString())
            ->assertSee('template.published')
            ->assertDontSee('guest.imported')
            ->set('from', 'not-a-date')
            ->assertSee('guest.imported');
    }

    public function test_entries_of_deleted_users_still_show(): void
    {
        $user = User::factory()->client()->create(['email' => 'gone@example.test']);
        app(AuditLogger::class)->record('auth.login', $user);
        $user->delete();

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(AuditLogViewer::class)
            ->assertSee('gone@example.test')
            ->assertSee('deleted');
    }

    public function test_subscription_changes_are_recorded(): void
    {
        $user = User::factory()->client()->create();
        $plan = Plan::factory()->create(['name' => 'Premium']);

        app(SubscriptionService::class)->grant($user, $plan, 30);

        $log = AuditLog::query()->where('action', 'subscription.granted')->sole();
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Premium', $log->metadata['plan']);
    }
}
