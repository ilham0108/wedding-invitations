<?php

namespace Tests\Feature;

use App\Enums\InvitationStatus;
use App\Livewire\InvitationEditor;
use App\Livewire\InvitationMusic;
use App\Livewire\MusicManager;
use App\Models\Invitation;
use App\Models\MusicTrack;
use App\Models\User;
use App\Services\MusicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesInvitations;
use Tests\Concerns\InteractsWithPlans;
use Tests\Concerns\MakesAudioFiles;
use Tests\TestCase;

class MusicTest extends TestCase
{
    use CreatesInvitations, InteractsWithPlans, MakesAudioFiles, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function publishedInvitation(): Invitation
    {
        $invitation = $this->createInvitation();
        $invitation->forceFill(['status' => InvitationStatus::Published, 'published_at' => now()])->save();

        return $invitation;
    }

    private function musicPanel(Invitation $invitation)
    {
        return Livewire::actingAs($invitation->user)->test(InvitationMusic::class, ['invitationUlid' => $invitation->ulid]);
    }

    public function test_only_admins_open_the_music_library(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('admin.music.index'))->assertOk()->assertSeeLivewire(MusicManager::class);
        $this->actingAs(User::factory()->client()->create())->get(route('admin.music.index'))->assertForbidden();
    }

    public function test_admins_upload_rename_and_flag_songs(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(MusicManager::class)
            ->set('uploadPremium', true)
            ->set('files', [$this->mp3('Canon in D.mp3')])
            ->call('saveUploads')
            ->assertHasNoErrors()
            ->assertDispatched('notify', type: 'success');

        $track = MusicTrack::query()->sole();
        $this->assertSame('Canon in D', $track->title);
        $this->assertTrue($track->is_premium);
        Storage::disk('public')->assertExists($track->path);

        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(MusicManager::class)
            ->call('rename', $track->ulid, 'artist', 'Pachelbel')
            ->call('rename', $track->ulid, 'title', '')
            ->call('togglePremium', $track->ulid);

        $track->refresh();
        $this->assertSame('Pachelbel', $track->artist);
        $this->assertSame('Canon in D', $track->title);
        $this->assertFalse($track->is_premium);
    }

    public function test_only_mp3_files_are_accepted(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(MusicManager::class)
            // In tests Livewire reports the faked MIME type; in production it sniffs the contents.
            ->set('files', [UploadedFile::fake()->create('song.mp3', 10, 'text/x-php')])
            ->call('saveUploads')
            ->assertHasErrors('files.0');

        $this->assertSame(0, MusicTrack::query()->count());
    }

    public function test_m4a_songs_named_mp3_are_stored_as_m4a_and_video_files_are_refused(): void
    {
        Livewire::actingAs(User::factory()->superAdmin()->create())
            ->test(MusicManager::class)
            ->set('files', [$this->m4a('Canon in D (YouTube).mp3')])
            ->call('saveUploads')
            ->assertHasNoErrors()
            ->set('files', [$this->m4a('clip.mp3', ['vide', 'soun'])])
            ->call('saveUploads')
            ->assertHasErrors('files.0');

        $track = MusicTrack::query()->sole();
        $this->assertStringEndsWith('.m4a', $track->path);
        Storage::disk('public')->assertExists($track->path);
    }

    public function test_clients_upload_m4a_songs(): void
    {
        $invitation = $this->createInvitation();
        $this->subscribe($invitation->user, ['custom_music' => true]);

        $this->musicPanel($invitation)->set('song', $this->m4a('ours.m4a'))->assertHasNoErrors()->assertSet('music.source', 'upload');

        $song = $invitation->media()->where('collection', 'music')->sole();
        $this->assertSame('audio/mp4', $song->mime_type);
        $this->assertStringEndsWith('.m4a', $song->path);
    }

    public function test_songs_in_use_are_hidden_instead_of_deleted(): void
    {
        $used = MusicTrack::factory()->create();
        $unused = MusicTrack::factory()->create();
        $invitation = $this->createInvitation();
        app(MusicService::class)->update($invitation, ['enabled' => true, 'track' => $used->ulid]);

        $manager = Livewire::actingAs(User::factory()->superAdmin()->create())->test(MusicManager::class);
        $manager->call('remove', $used->ulid)->assertDispatched('notify', type: 'warning');
        $manager->call('remove', $unused->ulid);

        $this->assertFalse($used->fresh()->is_active);
        $this->assertNull($unused->fresh());
    }

    public function test_clients_pick_a_library_song_and_the_page_plays_it(): void
    {
        $track = MusicTrack::factory()->create(['path' => 'music/library/waltz.mp3']);
        $invitation = $this->publishedInvitation();

        $this->musicPanel($invitation)
            ->assertSee($track->title)
            ->call('chooseTrack', $track->ulid)
            ->assertSet('music.enabled', true)
            ->assertSet('music.track', $track->ulid)
            ->set('music.start_at', 9999)
            ->assertSet('music.start_at', 600)
            ->assertDispatched('invitation-updated');

        $html = $this->get(route('invitations.public', $invitation->slug))->assertOk()->getContent();
        $this->assertStringContainsString('src="/storage/music/library/waltz.mp3"', $html);
        $this->assertStringContainsString('data-autoplay="1"', $html);
        $this->assertStringContainsString('data-start="600"', $html);

        $this->musicPanel($invitation)->set('music.enabled', false);
        $this->get(route('invitations.public', $invitation->slug))->assertDontSee('data-inv-music', false);
    }

    public function test_the_editor_preview_does_not_start_the_music_by_itself(): void
    {
        $track = MusicTrack::factory()->create();
        $invitation = $this->createInvitation();
        app(MusicService::class)->update($invitation, ['enabled' => true, 'track' => $track->ulid]);

        $this->actingAs($invitation->user)
            ->get(route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]))
            ->assertSee('data-autoplay="0"', false);
    }

    public function test_premium_and_hidden_songs_follow_the_plan_and_cannot_be_forced(): void
    {
        $this->freePlan(['premium_music' => false]);
        $premium = MusicTrack::factory()->premium()->create();
        $hidden = MusicTrack::factory()->hidden()->create();
        $free = MusicTrack::factory()->create();
        $invitation = $this->createInvitation();

        $this->musicPanel($invitation)
            ->call('chooseTrack', $premium->ulid)
            ->assertDispatched('notify', type: 'warning')
            ->assertSet('music.track', null)
            ->call('chooseTrack', $free->ulid)
            // Hand-made requests for the song itself are ignored; only the switches are saved.
            ->set('music.track', $premium->ulid)
            ->assertSet('music.track', $free->ulid);

        $this->musicPanel($invitation)->call('chooseTrack', $hidden->ulid)->assertNotFound();

        $this->subscribe($invitation->user, ['premium_music' => true]);
        $this->musicPanel($invitation)->call('chooseTrack', $premium->ulid)->assertSet('music.track', $premium->ulid);
    }

    public function test_uploading_an_own_song_needs_the_plan_feature(): void
    {
        $this->freePlan(['custom_music' => false]);
        $invitation = $this->createInvitation();

        $this->musicPanel($invitation)
            ->assertSee('Uploading your own song is part of a higher plan.')
            ->set('song', $this->mp3())
            ->assertForbidden();

        $this->assertSame(0, $invitation->media()->count());
    }

    public function test_an_uploaded_song_replaces_the_previous_one_and_is_played(): void
    {
        $invitation = $this->publishedInvitation();
        $this->subscribe($invitation->user, ['custom_music' => true]);

        $this->musicPanel($invitation)->set('song', $this->mp3('first.mp3'))->assertHasNoErrors();
        $first = $invitation->media()->where('collection', 'music')->sole();

        $this->musicPanel($invitation)
            ->set('song', $this->mp3('ours.mp3'))
            ->assertHasNoErrors()
            ->assertSet('music.source', 'upload')
            ->assertSee('ours.mp3');

        $song = $invitation->media()->where('collection', 'music')->sole();
        $this->assertNotSame($first->ulid, $song->ulid);
        Storage::disk('public')->assertMissing($first->path);
        $this->assertSame($song->ulid, $invitation->settings()->first()->music['upload']);

        $this->get(route('invitations.public', $invitation->slug))->assertSee('src="'.$song->url().'"', false);
    }

    public function test_the_page_ignores_songs_of_other_invitations(): void
    {
        $other = $this->createInvitation();
        $foreign = $other->media()->make(['collection' => 'music', 'disk' => 'public', 'path' => 'x.mp3', 'mime_type' => 'audio/mpeg', 'size' => 1]);
        $foreign->user()->associate($other->user_id);
        $foreign->save();

        $invitation = $this->createInvitation();
        app(MusicService::class)->update($invitation, ['enabled' => true, 'source' => 'upload', 'upload' => $foreign->ulid]);

        $this->assertNull(app(MusicService::class)->forPage($invitation->fresh()));
    }

    public function test_the_editor_has_a_music_tab(): void
    {
        $invitation = $this->createInvitation();

        Livewire::actingAs($invitation->user)
            ->test(InvitationEditor::class, ['invitation' => $invitation])
            ->set('panel', 'music')
            ->assertSeeLivewire(InvitationMusic::class);
    }
}
