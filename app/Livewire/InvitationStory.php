<?php

namespace App\Livewire;

use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Services\Invitation\StoryCard;
use Illuminate\View\View;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * Instagram Story page: the canvas preview and its options run in Alpine; save() stores the
 * owner's choices (also used by the guest button on the invitation page) without re-rendering.
 */
class InvitationStory extends Component
{
    use ResolvesEditableInvitation;

    public function mount(string $invitationUlid): void
    {
        $this->invitationUlid = $invitationUlid;
        $this->invitation();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed> the stored (cleaned) options
     */
    #[Renderless]
    public function save(array $options, StoryCard $stories): array
    {
        return $stories->save($this->invitation(), $options);
    }

    public function render(StoryCard $stories): View
    {
        $invitation = $this->invitation();

        return view('livewire.invitation-story', [
            'invitation' => $invitation,
            ...$stories->forInvitation($invitation),
            'maxHeadline' => StoryCard::MAX_HEADLINE,
            'maxMessage' => StoryCard::MAX_MESSAGE,
            'headlinePresets' => config('invitation.story_headlines'),
        ]);
    }
}
