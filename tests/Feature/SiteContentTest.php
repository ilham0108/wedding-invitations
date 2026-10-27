<?php

namespace Tests\Feature;

use App\Livewire\PageManager;
use App\Livewire\SiteContentManager;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SiteContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    private function manager(string $tab = 'hero')
    {
        return Livewire::actingAs($this->admin)->test(SiteContentManager::class)->set('tab', $tab);
    }

    public function test_the_home_page_shows_the_default_copy_until_the_admin_edits_it(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Undangan Digital untuk Momen Spesial Anda')
            ->assertSee('Mudah dan Praktis')
            ->assertSee('Pilih desain yang sesuai dengan tema acara.')
            ->assertDontSee('id="faq"', false);
    }

    public function test_only_admins_open_the_cms(): void
    {
        $this->actingAs($this->admin)->get(route('admin.content.index'))->assertOk()->assertSeeLivewire(SiteContentManager::class);
        $this->actingAs(User::factory()->client()->create())->get(route('admin.content.index'))->assertForbidden();

        Livewire::actingAs(User::factory()->client()->create())->test(SiteContentManager::class)->assertForbidden();
    }

    public function test_the_hero_is_validated_saved_and_shown(): void
    {
        $this->manager()
            ->set('form.title', '')
            ->call('save')
            ->assertHasErrors('form.title')
            ->set('form.title', 'Undangan Pernikahan Online')
            ->set('form.badge', '')
            ->set('form.primary_label', 'Coba Gratis')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $this->get(route('home'))
            ->assertSee('Undangan Pernikahan Online')
            ->assertSee('Coba Gratis')
            ->assertDontSee('Undangan digital, jadi mudah');

        $this->assertSame('hero', AuditLog::query()->where('action', 'cms.section_updated')->sole()->metadata['section']);
    }

    public function test_lists_are_edited_and_icons_must_come_from_the_list(): void
    {
        $component = $this->manager('features');
        $count = count($component->get('form.items'));

        $component->call('addItem')
            ->set("form.items.{$count}.title", 'Buku Tamu Digital')
            ->set("form.items.{$count}.icon", 'fa-solid fa-skull" onmouseover="alert(1)')
            ->call('save')
            ->assertHasErrors("form.items.{$count}.icon")
            ->set("form.items.{$count}.icon", 'fa-regular fa-heart')
            ->call('moveItem', $count, -1)
            ->call('removeItem', 0)
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('home'))->assertSee('Buku Tamu Digital')->assertDontSee('Template Cantik');
    }

    public function test_the_faq_appears_once_it_has_questions(): void
    {
        $this->manager('faq')
            ->call('addItem')
            ->set('form.items.0.question', 'Apakah bisa dicoba gratis?')
            ->set('form.items.0.answer', 'Bisa, paket Gratis tidak perlu kartu kredit.')
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertSee('id="faq"', false)
            ->assertSee('Apakah bisa dicoba gratis?')
            ->assertSee('#faq', false);
    }

    public function test_contact_details_show_in_the_footer_and_links_must_be_https(): void
    {
        $this->manager('contact')
            ->set('form.instagram', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors('form.instagram')
            ->set('form.instagram', 'https://instagram.com/invitefully')
            ->set('form.whatsapp', '+62 812 3456 7890')
            ->set('form.email', 'halo@invitefully.test')
            ->call('save')
            ->assertHasNoErrors();

        $this->get(route('home'))
            ->assertSee('https://instagram.com/invitefully', false)
            ->assertSee('https://wa.me/6281234567890', false)
            ->assertSee('halo@invitefully.test');
    }

    public function test_section_photos_replace_the_illustrations(): void
    {
        Storage::fake('public');

        $this->manager()->set('sectionImage', UploadedFile::fake()->image('hero.jpg', 800, 600))->assertHasNoErrors();

        $image = $this->manager()->get('form.image');
        $this->assertStringStartsWith('/storage/cms/', $image);
        $this->get(route('home'))->assertSee($image, false)->assertDontSee('14 . 12 . 2026');

        // Saving the text keeps the photo.
        $this->manager()->set('form.title', 'Undangan Baru')->call('save')->assertSet('form.image', $image);

        $this->manager()->call('removeSectionImage')->assertSet('form.image', null);
        Storage::disk('public')->assertMissing(substr($image, strlen('/storage/')));

        $this->manager('cta')->set('sectionImage', UploadedFile::fake()->image('cta.jpg', 1200, 600))->assertHasNoErrors();
        $this->assertStringStartsWith('/storage/cms/', $this->manager('cta')->get('form.image'));

        // Sections without a photo slot refuse uploads.
        $this->manager('faq')->set('sectionImage', UploadedFile::fake()->image('x.jpg', 400, 400))->assertNotFound();
    }

    public function test_pages_are_written_in_markdown_and_published(): void
    {
        Livewire::actingAs($this->admin)->test(PageManager::class)
            ->call('create')
            ->set('form.title', 'Syarat & Ketentuan')
            ->assertSet('form.slug', 'syarat-ketentuan')
            ->set('form.body', "## Akun\n\nIsi **penting**.\n\n<script>alert(1)</script>\n\n[klik](javascript:alert(1))")
            ->set('form.is_published', true)
            ->set('preview', true)
            ->assertSee('<strong>penting</strong>', false)
            ->call('save')
            ->assertHasNoErrors();

        $page = Page::query()->where('slug', 'syarat-ketentuan')->sole();

        $html = $this->get(route('pages.show', $page))->assertOk()->assertSee('<h2>Akun</h2>', false)->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);

        $this->get(route('home'))->assertSee(route('pages.show', $page), false);
    }

    public function test_draft_pages_are_not_public_and_slugs_are_unique(): void
    {
        $draft = Page::factory()->draft()->create(['slug' => 'kebijakan-privasi']);

        $this->get(route('pages.show', $draft))->assertNotFound();
        $this->get(route('home'))->assertDontSee(route('pages.show', $draft), false);

        Livewire::actingAs($this->admin)->test(PageManager::class)
            ->call('create')
            ->set('form.title', 'Privasi')
            ->set('form.slug', 'kebijakan-privasi')
            ->set('form.body', 'Teks')
            ->call('save')
            ->assertHasErrors('form.slug')
            ->set('form.slug', 'Bad Slug!')
            ->call('save')
            ->assertHasErrors('form.slug');
    }
}
