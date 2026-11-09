<?php

namespace Tests\Feature;

use App\Enums\TemplateStatus;
use App\Livewire\HtmlDesignImport;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use App\Models\UploadedDesign;
use App\Models\User;
use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\InvitationService;
use App\Services\Template\HtmlDesign\HtmlDesignImporter;
use App\Services\Template\HtmlDesign\HtmlDesignInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use ZipArchive;

/**
 * HTML design packages: checked first, stored only when clean, and shown as an ordinary draft template.
 */
class HtmlDesignImportTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    private const CSS = 'body { color: var(--inv-text); } .hero, h1 { background: url("assets/bg.png"); } @media (min-width: 600px) { .hero { padding: 2rem; } }';

    private const HTML = <<<'HTML'
        <!doctype html><html><body>
        <section id="cover" class="hero">
          <p data-inv-field="subtitle">The Wedding Of</p>
          <h1 data-inv-field="title">Alya &amp; Pradana</h1>
          <img data-inv-field="background_image" src="assets/bg.png" alt="">
          <a href="#" data-inv-effect="next">Lanjut</a>
          <p>Kepada: <inv-guest-name></inv-guest-name></p>
        </section>
        <section id="event">
          <h2 data-inv-field="heading">Waktu</h2>
          <article data-inv-repeat="events" class="card">
            <h3 data-inv-field="title">Akad</h3>
            <p data-inv-field="date" data-inv-value="2026-12-12">Sabtu</p>
            <p data-inv-field="venue">Gedung A</p>
          </article>
          <article data-inv-repeat="events" class="card">
            <h3 data-inv-field="title">Resepsi</h3>
            <p data-inv-field="venue">Gedung B</p>
          </article>
        </section>
        </body></html>
        HTML;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    public function test_a_clean_package_passes_and_turns_marked_parts_into_fields(): void
    {
        $result = $this->inspect();

        $this->assertSame([], $result['errors']);
        $this->assertSame(['cover', 'event'], $result['summary']['sections']);
        $this->assertSame('Alya & Pradana', $result['samples']['cover']['title']);
        $this->assertSame('/storage/designs/html-test/assets/bg.png', $result['samples']['cover']['background_image']);
        $this->assertSame(['Akad', 'Resepsi'], array_column($result['samples']['event']['events'], 'title'));
        $this->assertSame('2026-12-12', $result['samples']['event']['events'][0]['date']);
    }

    public function test_the_stylesheet_is_limited_to_the_design_and_its_files_are_rewritten(): void
    {
        $css = $this->inspect()['css'];

        $this->assertStringContainsString('.inv-design-html-test{', $css);
        $this->assertStringContainsString('.inv-design-html-test .hero,.inv-design-html-test h1{', $css);
        $this->assertStringContainsString('url("/storage/designs/html-test/assets/bg.png")', $css);
        $this->assertStringContainsString('@media (min-width: 600px){.inv-design-html-test .hero{', $css);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function badHtml(): array
    {
        return [
            'script' => ['<section id="cover"><script>alert(1)</script></section>', 'script'],
            'event handler' => ['<section id="cover"><div onclick="x()">a</div></section>', 'onclick'],
            'iframe' => ['<section id="cover"><iframe src="https://x.test"></iframe></section>', 'iframe'],
            'javascript link' => ['<section id="cover"><a href="javascript:alert(1)">a</a></section>', 'javascript:alert'],
            'external image' => ['<section id="cover"><img src="https://x.test/a.png" alt=""></section>', 'https://x.test/a.png'],
            'missing asset' => ['<section id="cover"><img src="assets/none.png" alt=""></section>', 'assets/none.png'],
            'unknown section' => ['<section id="acara"><p>a</p></section>', 'acara'],
            'unknown field' => ['<section id="cover"><p data-inv-field="judul">a</p></section>', 'judul'],
            'unknown effect' => ['<section id="cover"><p data-inv-effect="meledak">a</p></section>', 'meledak'],
            'style url' => ['<section id="cover"><p style="background:url(https://x.test/a)">a</p></section>', 'style'],
        ];
    }

    #[DataProvider('badHtml')]
    public function test_unsafe_or_wrong_html_is_reported(string $html, string $expected): void
    {
        $errors = implode(' ', $this->inspect(['index.html' => $html])['errors']);

        $this->assertStringContainsString($expected, $errors);
    }

    public function test_unsafe_stylesheets_and_files_are_reported(): void
    {
        foreach (['@import url("https://x.test/a.css");', 'a { background: url(https://x.test/a.png); }', 'a { behavior: url(x.htc); }'] as $css) {
            $this->assertNotSame([], $this->inspect(['style.css' => $css])['errors'], $css);
        }

        $this->assertNotSame([], $this->inspect(['assets/shell.php' => '<?php echo 1;'])['errors']);
        $this->assertNotSame([], $this->inspect(['assets/fake.png' => 'not an image'])['errors']);
        $this->assertNotSame([], $this->inspect(['index.html' => null])['errors']);
    }

    public function test_a_failing_package_stores_nothing(): void
    {
        $this->expectException(ValidationException::class);

        try {
            app(HtmlDesignImporter::class)->import($this->zip(['index.html' => '<section id="cover"><script></script></section>']), 'Rusak', TemplateCategory::factory()->create()->id, User::factory()->superAdmin()->create());
        } finally {
            $this->assertSame(0, UploadedDesign::query()->count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_an_imported_design_renders_with_escaped_values_and_its_own_stylesheet(): void
    {
        $template = $this->import();
        $key = $template->design;

        $this->assertSame(TemplateStatus::Draft, $template->status);
        Storage::disk('public')->assertExists("designs/{$key}/style.css");
        Storage::disk('public')->assertExists("designs/{$key}/assets/bg.png");

        $sample = $template->sample_content;
        $sample['cover']['title'] = '<img src=x onerror=alert(1)>';
        $template->update(['sample_content' => $sample, 'status' => TemplateStatus::Published]);

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

        $this->assertStringContainsString("/storage/designs/{$key}/style.css", $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringNotContainsString('<img src=x onerror', $html);
        $this->assertStringContainsString('Gedung B', $html);
        $this->assertStringContainsString('data-inv-next', $html);
    }

    public function test_design_wording_becomes_default_content_but_personal_details_stay_samples(): void
    {
        $template = $this->import();
        $asset = "/storage/designs/{$template->design}/assets/bg.png";

        $this->assertSame('The Wedding Of', $template->default_configuration['cover']['subtitle']);
        $this->assertSame($asset, $template->default_configuration['cover']['background_image']);
        $this->assertArrayNotHasKey('title', $template->default_configuration['cover']);
        $this->assertSame(['heading' => 'Waktu'], $template->default_configuration['event']);
        $this->assertSame('Alya & Pradana', $template->sample_content['cover']['title']);
        $this->assertCount(2, $template->sample_content['event']['events']);

        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Citra & Dimas', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);
        $cover = $invitation->sections()->where('type', 'cover')->first()->configuration;
        $event = $invitation->sections()->where('type', 'event')->first()->configuration;

        // The cover subtitle and title of a new invitation always come from the creation form.
        $this->assertSame($asset, $cover['background_image']);
        $this->assertSame('Citra & Dimas', $cover['title']);
        $this->assertSame('Waktu', $event['heading']);
        $this->assertStringNotContainsString('Gedung', (string) json_encode($event['events']));
    }

    public function test_sections_not_in_the_package_use_the_classic_view(): void
    {
        $key = $this->import()->design;

        $this->assertSame(['view' => 'invitation.sections.closing.default', 'native' => false], app(DesignRegistry::class)->sectionView($key, 'closing', 'default'));
        $this->assertSame(['view' => 'invitation.uploaded-section', 'native' => true], app(DesignRegistry::class)->sectionView($key, 'event', 'cards'));
    }

    public function test_only_super_admins_can_import(): void
    {
        $this->actingAs(User::factory()->client()->create())->get(route('admin.templates.import-html'))->assertForbidden();
        Livewire::actingAs(User::factory()->client()->create())->test(HtmlDesignImport::class)->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.templates.import-html'))->assertOk();
    }

    public function test_the_screen_reports_first_and_imports_after_confirmation(): void
    {
        $category = TemplateCategory::factory()->create();
        $file = UploadedFile::fake()->createWithContent('desain.zip', (string) file_get_contents($this->zip()));

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(HtmlDesignImport::class)
            ->set('package', $file)
            ->assertSee('The package passed the check.')
            ->assertSet('report.errors', [])
            ->set('name', 'Melati Emas')
            ->set('category', $category->slug)
            ->call('import')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertSame(1, InvitationTemplate::query()->where('name', 'Melati Emas')->count());
    }

    /**
     * @param  array<string, ?string>  $overrides
     * @return array<string, mixed>
     */
    private function inspect(array $overrides = []): array
    {
        return app(HtmlDesignInspector::class)->inspect($this->zip($overrides), 'html-test');
    }

    private function import(): InvitationTemplate
    {
        return app(HtmlDesignImporter::class)->import($this->zip(), 'Melati', TemplateCategory::factory()->create()->id, User::factory()->superAdmin()->create());
    }

    /**
     * @param  array<string, ?string>  $overrides
     */
    private function zip(array $overrides = []): string
    {
        $this->path = tempnam(sys_get_temp_dir(), 'design').'.zip';
        $zip = new ZipArchive;
        $zip->open($this->path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $files = [...['index.html' => self::HTML, 'style.css' => self::CSS, 'assets/bg.png' => base64_decode(self::PNG)], ...$overrides];

        foreach (array_filter($files, fn (?string $contents): bool => $contents !== null) as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $this->path;
    }
}
