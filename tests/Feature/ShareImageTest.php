<?php

namespace Tests\Feature;

use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\OgImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The picture shown when a link is shared (og:image): a 1200×630 JPEG chat apps can display (OgImageService).
 */
class ShareImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_a_template_preview_shares_its_cover_photo_as_a_jpeg(): void
    {
        $photo = $this->upload('covers/photo.webp', 1600, 2400);
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['cover']],
            'sample_content' => ['cover' => ['background_image' => $photo, 'background_image_focus' => '50% 20%']],
        ]);

        $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<meta property="og:image" content="http[^"]+/storage/og/[0-9a-f]{40}\.jpg">#', $html);
        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);

        $files = Storage::disk('public')->files('og');
        $this->assertCount(1, $files);
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($files[0]));
        $this->assertSame([1200, 630, IMAGETYPE_JPEG], [$width, $height, $type]);
        $this->assertLessThan(300 * 1024, Storage::disk('public')->size($files[0]));

        // Made once, reused afterwards.
        $this->get(route('templates.preview', $template))->assertOk();
        $this->assertCount(1, Storage::disk('public')->files('og'));
    }

    public function test_the_template_thumbnail_wins_over_the_cover(): void
    {
        $this->upload('covers/photo.webp', 800, 800);
        $thumbnail = 'templates/thumb.png';
        Storage::disk('public')->put($thumbnail, $this->image(900, 600, 'png'));
        $template = InvitationTemplate::factory()->create(['thumbnail' => $thumbnail, 'schema' => ['sections' => ['cover']]]);

        $this->get(route('templates.preview', $template))->assertOk()->assertSee('/storage/og/', false);
        $this->assertCount(1, Storage::disk('public')->files('og'));
    }

    public function test_invitations_share_their_cover_as_a_jpeg(): void
    {
        $photo = $this->upload('covers/couple.webp', 1200, 1600);
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['cover']], 'default_configuration' => ['cover' => ['background_image' => $photo]]]);
        $invitation = app(InvitationService::class)->createFromTemplate(User::factory()->client()->create(), $template, [
            'title' => 'Alya & Pradana', 'event_type' => 'wedding', 'timezone' => 'Asia/Jakarta',
        ]);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', $invitation))
            ->assertOk()
            ->assertSee('/storage/og/', false);
    }

    public function test_only_files_of_this_site_are_read(): void
    {
        $og = app(OgImageService::class);

        // Other sites are passed through, never fetched.
        $this->assertSame('https://example.com/photo.jpg', $og->url('https://example.com/photo.jpg'));
        // Paths that leave public/ or the public disk, or do not exist, stay as they are and make nothing.
        $this->assertSame('/storage/../../.env', $og->url('/storage/../../.env'));
        $this->assertSame('/../composer.json', $og->url('/../composer.json'));
        $this->assertSame('/storage/missing.webp', $og->url('/storage/missing.webp'));
        // A file that is not an image makes nothing either.
        Storage::disk('public')->put('notes.webp', 'not an image');
        $this->assertSame('/storage/notes.webp', $og->url('/storage/notes.webp'));

        $this->assertSame([], Storage::disk('public')->files('og'));
        $this->assertNull($og->url(null));
        // Chat apps cannot show SVG pictures.
        $this->assertNull($og->url('/images/demo/cover.svg'));
    }

    private function upload(string $path, int $width, int $height): string
    {
        Storage::disk('public')->put($path, $this->image($width, $height, 'webp'));

        return '/storage/'.$path;
    }

    private function image(int $width, int $height, string $format): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 90, 60));
        ob_start();
        $format === 'png' ? imagepng($image) : imagewebp($image, null, 80);

        return (string) ob_get_clean();
    }
}
