<?php

namespace Tests\Feature;

use App\Livewire\FontManager;
use App\Livewire\TemplateBuilder;
use App\Models\Font;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\FontRegistry;
use App\Services\Invitation\ThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FontManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    private function fakeBunny(): void
    {
        Http::fake(['fonts.bunny.net/list' => Http::response([
            'cormorant-infant' => ['familyName' => 'Cormorant Infant', 'category' => 'serif', 'weights' => [300, 400, 500, 600, 700], 'styles' => ['normal', 'italic']],
            'pacifico' => ['familyName' => 'Pacifico', 'category' => 'handwriting', 'weights' => [400], 'styles' => ['normal']],
        ])]);
    }

    private function manager()
    {
        return Livewire::actingAs($this->admin)->test(FontManager::class);
    }

    private function fontFile(string $name, string $signature = 'wOF2'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $signature.str_repeat("\0", 64));
    }

    public function test_only_admins_manage_fonts(): void
    {
        $this->actingAs($this->admin)->get(route('admin.fonts.index'))->assertOk()->assertSeeLivewire(FontManager::class)->assertSee('Playfair Display');
        $this->actingAs(User::factory()->client()->create())->get(route('admin.fonts.index'))->assertForbidden();
        Livewire::actingAs(User::factory()->client()->create())->test(FontManager::class)->assertForbidden();
    }

    public function test_a_bunny_font_is_found_by_name_then_installed(): void
    {
        $this->fakeBunny();

        $this->manager()
            ->set('bunnyName', 'Nope Sans')->call('lookup')->assertHasErrors('bunnyName')->assertSet('bunnyMatch', null)
            ->set('bunnyName', 'Playfair Display')->call('lookup')->assertHasErrors('bunnyName')
            ->set('bunnyName', 'pacifico')->call('lookup')->assertHasNoErrors()
            ->assertSet('bunnyMatch.name', 'Pacifico')->assertSet('bunnyCategory', 'handwritten')
            ->call('addBunny')->assertSet('bunnyMatch', null);

        $font = Font::query()->where('name', 'Pacifico')->firstOrFail();
        $this->assertSame(['bunny', 'pacifico:400', 'handwritten', 'cursive'], [$font->source, $font->bunny_family, $font->category, $font->fallback]);

        // Bunny-only themes keep the plain Bunny URL.
        $url = app(ThemeResolver::class)->fontStylesheetUrl(['font_heading' => 'Pacifico', 'font_body' => 'Lora']);
        $this->assertSame('https://fonts.bunny.net/css?family=pacifico:400|lora:400,500,600,700&display=swap', $url);
    }

    public function test_bunny_being_unreachable_is_reported(): void
    {
        Http::fake(['fonts.bunny.net/list' => Http::response('', 500)]);

        $this->manager()->set('bunnyName', 'Pacifico')->call('lookup')->assertHasErrors('bunnyName')->assertSet('bunnyMatch', null);
        $this->assertSame(0, Font::query()->count());
    }

    public function test_uploaded_files_are_checked_by_content_and_served_as_font_face_rules(): void
    {
        Storage::fake('public');

        $this->manager()
            ->set('uploadName', 'Brand Serif')
            ->set('files', [UploadedFile::fake()->createWithContent('evil.woff2', '<?php echo 1;')])
            ->call('saveUpload')->assertHasErrors('files.0');

        $this->manager()
            ->set('uploadName', 'Brand Serif')
            ->set('uploadCategory', 'serif')
            ->set('files', [$this->fontFile('BrandSerif-Regular.woff2'), $this->fontFile('BrandSerif-BoldItalic.ttf', "\x00\x01\x00\x00")])
            ->assertSet('fileWeights', [400, 700])->assertSet('fileStyles', ['normal', 'italic'])
            ->call('saveUpload')->assertHasNoErrors();

        $font = Font::query()->where('name', 'Brand Serif')->with('files')->firstOrFail();
        $this->assertSame([['woff2', 400, 'normal'], ['truetype', 700, 'italic']], $font->files->map(fn ($file) => [$file->format, $file->weight, $file->style])->all());
        $font->files->each(fn ($file) => Storage::disk('public')->assertExists($file->path));

        // A theme with an uploaded font points to this app's stylesheet, which imports Bunny for the rest.
        $url = app(ThemeResolver::class)->fontStylesheetUrl(['font_heading' => 'Brand Serif', 'font_body' => 'Lora']);
        $this->assertStringStartsWith(route('fonts.stylesheet'), $url);

        $css = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/css; charset=UTF-8')->getContent();
        $this->assertStringStartsWith('@import url("https://fonts.bunny.net/css?family=lora:400,500,600,700&display=swap");', $css);
        $this->assertStringContainsString("@font-face { font-family: 'Brand Serif'; src: url('{$font->files[0]->url()}') format('woff2'); font-weight: 400; font-style: normal;", $css);
        $this->assertStringContainsString("format('truetype'); font-weight: 700; font-style: italic;", $css);
        $this->assertStringNotContainsString('Nope', $this->get(route('fonts.stylesheet', ['families' => 'Nope|Brand Serif']))->getContent());
    }

    public function test_font_names_are_restricted_and_unique(): void
    {
        $this->manager()
            ->set('uploadName', "Evil'; } body { x")->set('files', [$this->fontFile('a.woff2')])
            ->call('saveUpload')->assertHasErrors('uploadName')
            ->set('uploadName', 'Lora')->call('saveUpload')->assertHasErrors('uploadName');

        $this->assertSame(0, Font::query()->count());
    }

    public function test_installed_fonts_work_in_themes_hidden_ones_leave_the_picker_and_used_ones_are_kept(): void
    {
        Storage::fake('public');
        $this->manager()->set('uploadName', 'Brand Serif')->set('files', [$this->fontFile('a.woff2')])->call('saveUpload');
        $font = Font::query()->where('name', 'Brand Serif')->firstOrFail();

        $template = InvitationTemplate::factory()->create();
        Livewire::actingAs($this->admin)->test(TemplateBuilder::class, ['template' => $template])
            ->set('panel', 'theme')
            ->assertSeeHtml('<option value="Brand Serif">')
            ->set('theme.font_heading', 'Brand Serif')->assertHasNoErrors()->call('save');
        $this->assertSame('Brand Serif', $template->fresh()->theme['font_heading']);
        $this->assertStringContainsString("--inv-font-heading: 'Brand Serif', Georgia, serif;", app(ThemeResolver::class)->cssVariables(app(ThemeResolver::class)->resolve($template->fresh()->theme)));

        // In use: cannot be deleted, only hidden; the template keeps rendering with it.
        $this->manager()->call('remove', $font->ulid);
        $this->assertModelExists($font);

        $this->manager()->call('toggleActive', $font->ulid);
        $this->assertFalse($font->fresh()->is_active);
        $this->assertFalse(app(FontRegistry::class)->forPicker()->flatten(1)->contains('name', 'Brand Serif'));
        $this->assertTrue(app(FontRegistry::class)->forPicker(['Brand Serif'])->flatten(1)->contains('name', 'Brand Serif'));
        $this->get(route('templates.preview', $template))->assertOk()->assertSee(route('fonts.stylesheet'), false);

        // Not in use any more: deleted together with its files.
        $template->update(['theme' => [...$template->theme, 'font_heading' => 'Lora']]);
        $this->manager()->call('remove', $font->ulid);
        $this->assertModelMissing($font);
        $this->assertSame([], Storage::disk('public')->allFiles('fonts'));
    }
}
