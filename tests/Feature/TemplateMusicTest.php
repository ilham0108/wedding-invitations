<?php

namespace Tests\Feature;

use App\Livewire\MusicManager;
use App\Livewire\TemplateBuilder;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\MusicTrack;
use App\Models\User;
use App\Services\Invitation\InvitationService;
use App\Services\Template\TemplateDraft;
use App\Services\Template\TemplateSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithPlans;
use Tests\TestCase;

class TemplateMusicTest extends TestCase
{
    use InteractsWithPlans, RefreshDatabase;

    private User $admin;

    private InvitationTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->superAdmin()->create();
        $this->template = InvitationTemplate::factory()->create([
            'schema' => ['sections' => [['type' => 'cover', 'variant' => 'centered', 'background' => 'default']]],
        ]);
    }

    private function builder(): mixed
    {
        return Livewire::actingAs($this->admin)->test(TemplateBuilder::class, ['template' => $this->template]);
    }

    private function newInvitation(User $owner): Invitation
    {
        return app(InvitationService::class)->createFromTemplate($owner, $this->template->fresh(), [
            'title' => 'Ana & Budi',
            'event_type' => 'wedding',
            'timezone' => 'Asia/Jakarta',
        ]);
    }

    public function test_admin_picks_default_music_in_the_builder_and_the_preview_plays_it(): void
    {
        $track = MusicTrack::factory()->create(['path' => 'music/library/waltz.mp3']);

        $this->builder()
            ->set('panel', 'music')
            ->assertSee($track->title)
            ->call('chooseMusic', $track->ulid)
            ->assertSet('music.track', $track->ulid)
            ->set('music.start_at', 15)
            ->set('music.loop', false)
            ->assertSet('hasChanges', true)
            ->assertDispatched('template-preview-refresh');

        $this->assertSame(['track' => $track->ulid, 'start_at' => 15, 'loop' => false], app(TemplateDraft::class)->definition($this->template, $this->admin)['music']);
        $this->assertNull($this->template->fresh()->music);

        $html = $this->actingAs($this->admin)->get(route('admin.templates.builder.preview', $this->template))->assertOk()->getContent();
        $this->assertStringContainsString('src="/storage/music/library/waltz.mp3"', $html);
        $this->assertStringContainsString('data-start="15"', $html);
        $this->assertStringContainsString('data-autoplay="1"', $html);
    }

    public function test_saved_default_music_plays_on_the_public_template_preview(): void
    {
        $track = MusicTrack::factory()->create(['path' => 'music/library/waltz.mp3']);

        $this->builder()->call('chooseMusic', $track->ulid)->call('save');

        $this->assertSame($track->ulid, $this->template->fresh()->music['track']);
        $this->get(route('templates.preview', $this->template))->assertOk()->assertSee('src="/storage/music/library/waltz.mp3"', false);
    }

    public function test_music_can_be_removed_and_the_song_cannot_be_forced(): void
    {
        $track = MusicTrack::factory()->create();
        $other = MusicTrack::factory()->create();
        $hidden = MusicTrack::factory()->hidden()->create();

        $builder = $this->builder()
            ->call('chooseMusic', $track->ulid)
            // A hand-made request for the song itself is ignored.
            ->set('music.track', $other->ulid)
            ->assertSet('music.track', $track->ulid);

        $builder->call('chooseMusic', $hidden->ulid)->assertNotFound();

        $this->builder()->call('removeMusic')->assertSet('music.track', null);
        $this->assertSame([], app(TemplateDraft::class)->definition($this->template, $this->admin)['music']);
        $this->actingAs($this->admin)->get(route('admin.templates.builder.preview', $this->template))->assertDontSee('data-inv-music', false);
    }

    public function test_new_invitations_start_with_the_template_music_their_plan_allows(): void
    {
        $this->freePlan(['premium_music' => false]);
        $free = MusicTrack::factory()->create();
        $premium = MusicTrack::factory()->premium()->create();

        $this->template->update(['music' => ['track' => $free->ulid, 'start_at' => 20, 'loop' => true]]);
        $music = $this->newInvitation(User::factory()->client()->create())->settings->music;
        $this->assertEquals(['enabled' => true, 'source' => 'library', 'track' => $free->ulid, 'upload' => null, 'start_at' => 20, 'loop' => true], $music);

        $this->template->update(['music' => ['track' => $premium->ulid]]);
        $this->assertNull($this->newInvitation(User::factory()->client()->create())->settings->music);

        $subscriber = User::factory()->client()->create();
        $this->subscribe($subscriber, ['premium_music' => true]);
        $this->assertSame($premium->ulid, $this->newInvitation($subscriber)->settings->music['track']);

        $premium->update(['is_active' => false]);
        $this->assertNull($this->newInvitation($subscriber)->settings->music);
    }

    public function test_unknown_songs_are_dropped_from_a_definition(): void
    {
        $definition = app(TemplateSchema::class)->validate([...$this->template->definition(), 'music' => ['track' => '01jzzzzzzzzzzzzzzzzzzzzzzz']]);

        $this->assertSame([], $definition['music']);
    }

    public function test_songs_used_by_a_template_are_hidden_instead_of_deleted(): void
    {
        $track = MusicTrack::factory()->create();
        $this->template->update(['music' => ['track' => $track->ulid]]);

        Livewire::actingAs($this->admin)->test(MusicManager::class)->call('remove', $track->ulid)->assertDispatched('notify', type: 'warning');

        $this->assertFalse($track->fresh()->is_active);
    }
}
