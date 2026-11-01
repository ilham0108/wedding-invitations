<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Enums\TemplateStatus;
use App\Livewire\TemplateManager;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\MusicTrack;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\Template\TemplateDraft;
use App\Services\Template\TemplatePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\MakesAudioFiles;
use Tests\TestCase;
use ZipArchive;

class TemplatePackageTest extends TestCase
{
    use MakesAudioFiles, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * A template using an uploaded cover, an uploaded ornament, a bundled illustration and a thumbnail.
     */
    private function template(): InvitationTemplate
    {
        Storage::disk('public')->put('invitations/x/cover/photo.webp', $this->png());
        Storage::disk('public')->put('illustrations/ornament/leaf.png', $this->png());
        Storage::disk('public')->put('templates/thumbnails/thumb.webp', $this->png());

        $category = TemplateCategory::factory()->create(['slug' => 'modern-wedding', 'name' => 'Modern Wedding']);

        return InvitationTemplate::factory()->for($category, 'category')->create([
            'name' => 'Garden Party',
            'slug' => 'garden-party',
            'is_premium' => true,
            'thumbnail' => 'templates/thumbnails/thumb.webp',
            'schema' => ['sections' => [
                ['type' => 'cover', 'ornaments' => [['image' => '/storage/illustrations/ornament/leaf.png', 'x' => 10, 'y' => 0]]],
                'quote',
            ]],
            'decoration' => ['animation' => 'fade-up'],
            'default_configuration' => ['cover' => ['background_image' => '/storage/invitations/x/cover/photo.webp']],
            'sample_content' => ['cover' => ['title' => 'Ana & Budi', 'background_image' => '/illustrations/cover-garden.svg']],
        ]);
    }

    private function export(InvitationTemplate $template): string
    {
        return app(TemplatePackage::class)->export($template, $this->admin);
    }

    /**
     * @param  array<string, string>  $files
     */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($files as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
    }

    private function manifest(array $overrides = []): string
    {
        return json_encode([
            'format' => TemplatePackage::FORMAT,
            'version' => 1,
            'template' => ['name' => 'Bad', 'slug' => 'bad', 'definition' => ['schema' => ['sections' => ['cover']]]],
            'assets' => [],
            ...$overrides,
        ]);
    }

    private function assertImportFails(string $path, string $message): void
    {
        try {
            app(TemplatePackage::class)->import($path, $this->admin);
            $this->fail('The import should have failed.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, collect($exception->errors())->flatten()->first());
        }
    }

    public function test_the_export_packs_the_configuration_and_uploaded_images_only(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->export($this->template()));
        $manifest = json_decode($zip->getFromName('template.json'), true);

        $this->assertSame(TemplatePackage::FORMAT, $manifest['format']);
        $this->assertSame('garden-party', $manifest['template']['slug']);
        $this->assertSame('modern-wedding', $manifest['template']['category']['slug']);
        $this->assertSame('fade-up', $manifest['template']['definition']['decoration']['animation']);
        $this->assertEqualsCanonicalizing(['/storage/invitations/x/cover/photo.webp', '/storage/illustrations/ornament/leaf.png'], array_keys($manifest['assets']));
        $this->assertSame('assets/thumbnail.webp', $manifest['thumbnail']);
        $this->assertSame(4, $zip->numFiles);
        $zip->close();
    }

    public function test_only_admins_download_an_export(): void
    {
        $template = $this->template();

        $this->actingAs($this->admin)->get(route('admin.templates.export', $template->ulid))
            ->assertOk()
            ->assertDownload('template-garden-party.zip');

        $this->actingAs(User::factory()->client()->create())->get(route('admin.templates.export', $template->ulid))->assertForbidden();
    }

    public function test_an_exported_template_imports_as_a_draft_with_its_own_copies_of_the_images(): void
    {
        $original = $this->template();
        $imported = app(TemplatePackage::class)->import($this->export($original), $this->admin);

        $this->assertNotSame($original->id, $imported->id);
        $this->assertSame('Garden Party', $imported->name);
        $this->assertNotSame('garden-party', $imported->slug);
        $this->assertSame(TemplateStatus::Draft, $imported->status);
        $this->assertTrue($imported->is_premium);
        $this->assertSame($original->template_category_id, $imported->template_category_id);
        $this->assertSame('fade-up', $imported->decoration['animation']);

        // Uploaded images point at new copies; bundled files keep their path.
        $cover = $imported->default_configuration['cover']['background_image'];
        $ornament = $imported->schema['sections'][0]['ornaments'][0]['image'];
        $this->assertStringStartsWith('/storage/templates/imported/', $cover);
        $this->assertStringStartsWith('/storage/templates/imported/', $ornament);
        Storage::disk('public')->assertExists(substr($cover, strlen('/storage/')));
        Storage::disk('public')->assertExists(substr($ornament, strlen('/storage/')));
        Storage::disk('public')->assertExists($imported->thumbnail);
        $this->assertSame('/illustrations/cover-garden.svg', $imported->sample_content['cover']['background_image']);
    }

    public function test_a_missing_category_is_created_inactive(): void
    {
        $path = $this->zip(['template.json' => $this->manifest(['template' => [
            'name' => 'Birthday Fun', 'slug' => 'birthday-fun',
            'category' => ['slug' => 'kids-party', 'name' => 'Kids Party', 'event_type' => 'birthday'],
            'definition' => ['schema' => ['sections' => ['cover']]],
        ]])]);

        $template = app(TemplatePackage::class)->import($path, $this->admin);

        $this->assertSame('kids-party', $template->category->slug);
        $this->assertFalse($template->category->is_active);
        $this->assertSame('birthday', $template->category->event_type->value);
    }

    public function test_broken_or_foreign_archives_are_refused(): void
    {
        $this->assertImportFails($this->zip(['readme.txt' => 'hi']), 'template.json');
        $this->assertImportFails($this->zip(['template.json' => '{"format":"other"}']), 'template.json');
        $this->assertImportFails($this->zip(['template.json' => $this->manifest(['version' => 99])]), 'newer version');

        $notZip = tempnam(sys_get_temp_dir(), 'zip');
        file_put_contents($notZip, 'not a zip');
        $this->assertImportFails($notZip, '.zip');

        $this->assertSame(0, InvitationTemplate::query()->count());
    }

    public function test_dangerous_entries_are_refused(): void
    {
        // A script disguised as an image.
        $this->assertImportFails($this->zip([
            'template.json' => $this->manifest(['assets' => ['/storage/a/b.png' => 'assets/1.png']]),
            'assets/1.png' => '<?php system($_GET["c"]);',
        ]), 'PNG, JPG or WebP');

        // Entries outside assets/ (zip slip) and URLs that are not uploaded files.
        $this->assertImportFails($this->zip([
            'template.json' => $this->manifest(['assets' => ['/storage/a/b.png' => '../../evil.png']]),
        ]), 'invalid file');
        $this->assertImportFails($this->zip([
            'template.json' => $this->manifest(['assets' => ['/storage/../../.env' => 'assets/1.png']]),
            'assets/1.png' => $this->png(),
        ]), 'invalid file');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_invalid_configuration_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(TemplatePackage::class)->import($this->zip(['template.json' => $this->manifest(['template' => [
            'name' => 'Bad', 'definition' => ['design' => '../../etc', 'schema' => ['sections' => ['cover']]],
        ]])]), $this->admin);
    }

    public function test_admins_import_from_the_template_manager(): void
    {
        $package = $this->export($this->template());

        Livewire::actingAs($this->admin)
            ->test(TemplateManager::class)
            ->set('package', UploadedFile::fake()->createWithContent('garden.zip', file_get_contents($package)))
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success')
            ->assertSet('package', null);

        $this->assertSame(2, InvitationTemplate::query()->where('name', 'Garden Party')->count());

        Livewire::actingAs($this->admin)
            ->test(TemplateManager::class)
            ->set('package', UploadedFile::fake()->createWithContent('broken.zip', file_get_contents($this->zip(['x.txt' => 'x']))))
            ->assertHasErrors('package')
            // The progress bar is closed whatever the outcome.
            ->assertDispatched('template-import-finished');
    }

    public function test_the_heading_ornament_image_and_the_music_travel_with_the_template(): void
    {
        Storage::disk('public')->put('illustrations/ornament/divider.png', $this->png());
        Storage::disk('public')->put('music/library/waltz.mp3', $this->mp3()->getContent());
        $ornament = Illustration::factory()->create(['category' => IllustrationCategory::Ornament, 'name' => 'Divider', 'disk' => 'public', 'path' => 'illustrations/ornament/divider.png']);
        $track = MusicTrack::factory()->create(['title' => 'Waltz', 'artist' => 'Ana', 'path' => 'music/library/waltz.mp3', 'is_premium' => true]);

        $template = $this->template();
        $template->update([
            'decoration' => ['heading_ornament' => 'image', 'heading_ornament_image' => $ornament->ulid],
            'music' => ['track' => $track->ulid, 'start_at' => 12, 'loop' => false],
        ]);

        $package = $this->export($template->fresh());
        $zip = new ZipArchive;
        $zip->open($package);
        $manifest = json_decode($zip->getFromName('template.json'), true);
        $this->assertSame('Divider', $manifest['library']['heading_ornament']['name']);
        $this->assertSame(['entry' => 'library/music.mp3', 'title' => 'Waltz', 'artist' => 'Ana', 'is_premium' => true], $manifest['library']['music']);
        $this->assertNotFalse($zip->getFromName('library/heading-ornament.png'));
        $zip->close();

        // Another installation: the library entries do not exist there.
        $ornament->forceDelete();
        $track->delete();
        Storage::disk('public')->deleteDirectory('illustrations');
        Storage::disk('public')->deleteDirectory('music');

        $imported = app(TemplatePackage::class)->import($package, $this->admin);

        $newOrnament = Illustration::query()->where('ulid', $imported->decoration['heading_ornament_image'])->firstOrFail();
        $this->assertSame([IllustrationCategory::Ornament, 'Divider'], [$newOrnament->category, $newOrnament->name]);
        Storage::disk('public')->assertExists($newOrnament->path);

        $newTrack = MusicTrack::query()->where('ulid', $imported->music['track'])->firstOrFail();
        $this->assertSame(['Waltz', 'Ana', true], [$newTrack->title, $newTrack->artist, $newTrack->is_premium]);
        $this->assertStringEndsWith('.mp3', $newTrack->path);
        Storage::disk('public')->assertExists($newTrack->path);
        $this->assertSame(['track' => $newTrack->ulid, 'start_at' => 12, 'loop' => false], $imported->music);
    }

    public function test_importing_again_on_the_same_installation_reuses_the_library_copies(): void
    {
        Storage::disk('public')->put('illustrations/ornament/divider.png', $this->png());
        Storage::disk('public')->put('music/library/waltz.mp3', $this->mp3()->getContent());
        $ornament = Illustration::factory()->create(['category' => IllustrationCategory::Ornament, 'disk' => 'public', 'path' => 'illustrations/ornament/divider.png']);
        $track = MusicTrack::factory()->create(['path' => 'music/library/waltz.mp3', 'size' => strlen($this->mp3()->getContent())]);

        $template = $this->template();
        $template->update([
            'decoration' => ['heading_ornament' => 'image', 'heading_ornament_image' => $ornament->ulid],
            'music' => ['track' => $track->ulid],
        ]);
        $package = $this->export($template->fresh());

        $first = app(TemplatePackage::class)->import($package, $this->admin);
        $second = app(TemplatePackage::class)->import($package, $this->admin);

        $this->assertSame([$ornament->ulid, $ornament->ulid], [$first->decoration['heading_ornament_image'], $second->decoration['heading_ornament_image']]);
        $this->assertSame([$track->ulid, $track->ulid], [$first->music['track'], $second->music['track']]);
        $this->assertSame(1, Illustration::query()->where('category', IllustrationCategory::Ornament)->count());
        $this->assertSame(1, MusicTrack::query()->count());
    }

    public function test_bundled_library_items_are_matched_by_their_path(): void
    {
        $ornament = Illustration::factory()->create(['category' => IllustrationCategory::Ornament, 'disk' => null, 'path' => 'illustrations/ornament-cream-blossom-corner.svg']);
        $template = $this->template();
        $template->update(['decoration' => ['heading_ornament' => 'image', 'heading_ornament_image' => $ornament->ulid]]);

        $package = $this->export($template->fresh());

        $this->assertSame($ornament->ulid, app(TemplatePackage::class)->import($package, $this->admin)->decoration['heading_ornament_image']);
        $this->assertSame(1, Illustration::query()->where('path', 'illustrations/ornament-cream-blossom-corner.svg')->count());

        // Not installed there: the headings fall back to the design's own ornament.
        $ornament->forceDelete();
        $decoration = app(TemplatePackage::class)->import($package, $this->admin)->decoration;
        $this->assertSame(['design', ''], [$decoration['heading_ornament'], $decoration['heading_ornament_image']]);
    }

    public function test_packed_music_must_really_be_audio(): void
    {
        $path = $this->zip([
            'template.json' => $this->manifest(['library' => ['music' => ['entry' => 'library/music.mp3', 'title' => 'Fake']]]),
            'library/music.mp3' => '<?php echo "not a song";',
        ]);

        $this->assertImportFails($path, __('The archive contains music that is not an MP3 or M4A file.'));
        $this->assertSame(0, MusicTrack::query()->count());
    }

    public function test_exporting_a_template_with_unsaved_builder_changes_asks_first(): void
    {
        $template = $this->template();

        Livewire::actingAs($this->admin)->test(TemplateManager::class)->assertDontSee(__('Unsaved changes'));

        app(TemplateDraft::class)->update($template, $this->admin, fn (array $definition): array => [...$definition, 'decoration' => ['animation' => 'zoom']]);

        Livewire::actingAs($this->admin)->test(TemplateManager::class)
            ->assertSee(__('Unsaved changes'))
            ->assertSee(__('This template has builder changes that are not saved yet. The export only contains the saved version. Export anyway?'));
    }
}
