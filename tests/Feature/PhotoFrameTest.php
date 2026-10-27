<?php

namespace Tests\Feature;

use App\Enums\IllustrationCategory;
use App\Livewire\SectionSettings;
use App\Livewire\TemplateSectionContent;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\Invitation\InvitationRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\TestCase;

/**
 * Couple portraits: photo shape (mask) and a frame image laid over the photo.
 */
class PhotoFrameTest extends TestCase
{
    use CreatesInvitations, RefreshDatabase;

    private const PERSON = ['name' => 'Arina', 'photo' => '/illustrations/person-bride.svg'];

    public function test_shape_frame_and_inset_are_cleaned(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['couple']],
            'sample_content' => ['couple' => ['photo_shape' => 'hexagon', 'photo_frame' => 'javascript:alert(1)', 'photo_frame_inset' => 90, 'people' => [self::PERSON]]],
        ]);

        $config = app(InvitationRenderer::class)->forTemplatePreview($template)->sections[0]['config'];

        $this->assertSame('default', $config['photo_shape']);
        $this->assertNull($config['photo_frame']);
        $this->assertSame(40.0, $config['photo_frame_inset']);
    }

    public function test_portraits_keep_the_design_look_until_a_shape_or_frame_is_chosen(): void
    {
        foreach (['classic', 'mocha-glass'] as $design) {
            $template = InvitationTemplate::factory()->create([
                'design' => $design,
                'schema' => ['sections' => ['couple']],
                'sample_content' => ['couple' => ['people' => [self::PERSON]]],
            ]);

            $this->get(route('templates.preview', $template))->assertOk()
                ->assertSee('person-bride.svg', false)
                ->assertDontSee('inv-photo', false);
        }
    }

    public function test_framed_portraits_render_in_every_design_and_variant(): void
    {
        foreach (['classic', 'mocha-glass'] as $design) {
            foreach (['side-by-side', 'stacked'] as $variant) {
                $template = InvitationTemplate::factory()->create([
                    'design' => $design,
                    'schema' => ['sections' => [['type' => 'couple', 'variant' => $variant]]],
                    'sample_content' => ['couple' => [
                        'photo_shape' => 'arch',
                        'photo_frame' => '/illustrations/frame-arch-gold.svg',
                        'photo_frame_inset' => 15,
                        'people' => [self::PERSON, [...self::PERSON, 'name' => 'Bima']],
                    ]],
                ]);

                $html = $this->get(route('templates.preview', $template))->assertOk()->getContent();

                $this->assertSame(2, substr_count($html, 'class="inv-photo__frame"'), "[{$design}/{$variant}]");
                $this->assertStringContainsString('inv-photo--arch', $html, "[{$design}/{$variant}]");
                $this->assertStringContainsString('--inv-photo-inset: 15%', $html, "[{$design}/{$variant}]");
            }
        }
    }

    public function test_a_frame_without_a_shape_masks_the_photo_as_an_oval(): void
    {
        $template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => ['couple']],
            'sample_content' => ['couple' => ['photo_frame' => '/illustrations/frame-oval-gold.svg', 'people' => [self::PERSON]]],
        ]);

        $this->get(route('templates.preview', $template))->assertOk()
            ->assertSee('inv-photo--oval', false)
            ->assertSee('--inv-photo-inset: 12%', false);
    }

    public function test_the_frame_picker_in_the_builder_offers_frames_only(): void
    {
        $template = InvitationTemplate::factory()->create(['schema' => ['sections' => ['couple']]]);
        $frame = Illustration::factory()->category(IllustrationCategory::Frame)->create(['name' => 'Gold Oval', 'path' => 'illustrations/frame-oval-gold.svg']);
        Illustration::factory()->category(IllustrationCategory::Ornament)->create(['name' => 'Corner Flowers']);

        Livewire::actingAs(User::factory()->superAdmin()->create())->test(TemplateSectionContent::class, [
            'templateUlid' => $template->ulid, 'type' => 'couple', 'group' => 'default_configuration',
        ])
            ->assertSee('Photo shape')
            ->call('openPicker', 'photo_frame')
            ->assertSee('Gold Oval')
            ->assertDontSee('Corner Flowers')
            ->call('pickIllustration', $frame->ulid)
            ->assertSet('config.photo_frame', '/illustrations/frame-oval-gold.svg');
    }

    public function test_clients_neither_see_nor_change_the_template_only_fields(): void
    {
        $invitation = $this->createInvitation();
        $couple = $invitation->sections()->where('type', 'couple')->first();
        $couple->update(['configuration' => [...$couple->configuration, 'photo_shape' => 'arch', 'photo_frame' => '/illustrations/frame-arch-gold.svg']]);

        $settings = fn (string $sectionUlid) => Livewire::actingAs($invitation->user)->test(SectionSettings::class, [
            'invitationUlid' => $invitation->ulid, 'sectionUlid' => $sectionUlid,
        ]);

        // A hand-made request for a hidden field is saved without it.
        $settings($couple->ulid)
            ->assertDontSee('Photo shape')
            ->assertDontSee('Photo frame')
            ->set('config.photo_shape', 'circle')
            ->set('config.photo_frame', 'https://evil.test/frame.png')
            ->set('config.heading', 'Kami Berdua')
            ->assertHasNoErrors();

        $settings($couple->ulid)->call('openPicker', 'photo_frame')->assertNotFound();

        $saved = $couple->fresh()->configuration;
        $this->assertSame('Kami Berdua', $saved['heading']);
        $this->assertSame('arch', $saved['photo_shape']);
        $this->assertSame('/illustrations/frame-arch-gold.svg', $saved['photo_frame']);

        $settings($couple->ulid)->assertSee('Background photo (changes while scrolling)')->assertDontSee('Background photo: overlay');
    }
}
