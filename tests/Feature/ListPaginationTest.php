<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\IllustrationManager;
use App\Livewire\InvitationCreator;
use App\Livewire\MusicManager;
use App\Livewire\PageManager;
use App\Livewire\PlanManager;
use App\Livewire\TemplateCategoryManager;
use App\Livewire\TemplateManager;
use App\Livewire\UserDetail;
use App\Livewire\WhatsAppProviderManager;
use App\Livewire\WhatsAppTemplateManager;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every list in the dashboards is paged on the server, so a growing table never loads in full.
 */
class ListPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_lists_receive_one_page_from_the_server(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $lists = [
            [TemplateManager::class, [], 'templates', 20],
            [IllustrationManager::class, [], 'illustrations', 35],
            [MusicManager::class, [], 'tracks', 20],
            [TemplateCategoryManager::class, [], 'categories', 20],
            [PlanManager::class, [], 'plans', 12],
            [PageManager::class, [], 'pages', 20],
            [WhatsAppProviderManager::class, [], 'providers', 20],
            [UserDetail::class, ['user' => User::factory()->client()->create()], 'invitations', 10],
        ];

        foreach ($lists as [$component, $parameters, $key, $perPage]) {
            $list = Livewire::actingAs($admin)->test($component, $parameters)->viewData($key);

            $this->assertInstanceOf(LengthAwarePaginator::class, $list, $component);
            $this->assertSame($perPage, $list->perPage(), $component);
        }
    }

    public function test_client_lists_receive_one_page_from_the_server(): void
    {
        $client = User::factory()->client()->create();
        InvitationTemplate::factory()->count(3)->create();

        $whatsappTemplates = Livewire::actingAs($client)->test(WhatsAppTemplateManager::class)->viewData('templates');
        $this->assertInstanceOf(LengthAwarePaginator::class, $whatsappTemplates);

        $picker = Livewire::actingAs($client)->test(InvitationCreator::class)->viewData('templates');
        $this->assertInstanceOf(LengthAwarePaginator::class, $picker);
        $this->assertSame(3, $picker->total());

        $orders = $this->actingAs($client)->get(route('dashboard.subscription.index'))->assertOk()->viewData('orders');
        $this->assertInstanceOf(LengthAwarePaginator::class, $orders);
    }

    public function test_the_client_template_gallery_is_filtered_and_paged_on_the_server(): void
    {
        $client = User::factory()->client()->create();
        $templates = InvitationTemplate::factory()->count(22)->create();
        $category = $templates->first()->category;

        $firstPage = $this->actingAs($client)->get(route('dashboard.templates.index'))->assertOk()->viewData('templates');
        $this->assertSame(20, $firstPage->count());
        $this->assertSame(22, $firstPage->total());

        $this->get(route('dashboard.templates.index', ['page' => 2]))->assertOk();
        $this->assertSame(2, $this->get(route('dashboard.templates.index', ['page' => 2]))->viewData('templates')->count());

        // Only templates of the chosen category; an unknown category shows everything.
        $filtered = $this->get(route('dashboard.templates.index', ['category' => $category->slug]))->viewData('templates');
        $this->assertTrue($filtered->getCollection()->every(fn ($template): bool => $template->category->slug === $category->slug));
        $this->assertSame(22, $this->get(route('dashboard.templates.index', ['category' => 'nope']))->viewData('templates')->total());
    }

    public function test_illustration_counts_cover_every_row_while_the_list_is_paged(): void
    {
        foreach (range(1, 40) as $number) {
            Illustration::query()->create([
                'category' => IllustrationCategory::Ornament, 'name' => "Ornamen {$number}", 'disk' => null, 'path' => 'illustrations/ornament-flower-corner.svg',
                'width' => 400, 'height' => 400, 'is_premium' => false, 'is_active' => true, 'sort_order' => $number,
            ]);
        }

        $manager = Livewire::actingAs(User::factory()->superAdmin()->create())->test(IllustrationManager::class);

        $this->assertSame(35, $manager->viewData('illustrations')->count());
        $this->assertSame(40, $manager->viewData('counts')[IllustrationCategory::Ornament->value]);

        // Changing the filter goes back to the first page.
        $manager->call('gotoPage', 2)->assertSet('paginators.page', 2)->set('category', 'ornament')->assertSet('paginators.page', 1);
    }
}
