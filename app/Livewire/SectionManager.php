<?php

namespace App\Livewire;

use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Services\Invitation\InvitationSectionService;
use App\Services\Invitation\SectionRegistry;
use Illuminate\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Section list: select, enable/disable, drag reorder, duplicate, delete, add (rules.md section 16).
 */
class SectionManager extends Component
{
    use ResolvesEditableInvitation;

    #[Reactive]
    public ?string $selected = null;

    public function mount(string $invitationUlid, ?string $selected = null): void
    {
        $this->invitationUlid = $invitationUlid;
        $this->selected = $selected;
        $this->invitation();
    }

    public function select(string $ulid, InvitationSectionService $sections): void
    {
        $sections->find($this->invitation(), $ulid);
        $this->dispatch('section-selected', ulid: $ulid);
    }

    public function add(string $type, SectionRegistry $registry, InvitationSectionService $sections): void
    {
        if (! $registry->has($type)) {
            return;
        }

        $section = $sections->add($this->invitation(), $type);
        $this->changed($section->ulid);
        $this->dispatch('section-selected', ulid: $section->ulid);
    }

    public function toggle(string $ulid, InvitationSectionService $sections): void
    {
        $sections->toggle($sections->find($this->invitation(), $ulid));
        $this->changed($ulid);
    }

    public function duplicate(string $ulid, InvitationSectionService $sections): void
    {
        $copy = $sections->duplicate($sections->find($this->invitation(), $ulid));
        $this->changed($copy->ulid);
        $this->dispatch('section-selected', ulid: $copy->ulid);
    }

    public function remove(string $ulid, InvitationSectionService $sections): void
    {
        $sections->delete($sections->find($this->invitation(), $ulid));

        $next = $this->invitation()->sections()->value('ulid');
        $this->dispatch('section-removed', next: $next);
        $this->changed($next);
    }

    /**
     * wire:sort handler: receives the dragged item key and its new zero-based position.
     */
    public function reorder(string $ulid, int $position, InvitationSectionService $sections): void
    {
        $sections->move($this->invitation(), $ulid, $position);
        $this->changed($ulid);
    }

    public function render(SectionRegistry $registry): View
    {
        return view('livewire.section-manager', [
            'sections' => $this->invitation()->sections()->get(['id', 'ulid', 'type', 'is_enabled', 'sort_order', 'configuration']),
            'registry' => $registry,
            'addableTypes' => collect($registry->types())->mapWithKeys(fn (string $type): array => [$type => $registry->label($type)]),
        ]);
    }

    private function changed(?string $sectionUlid): void
    {
        $this->dispatch('invitation-updated', section: $sectionUlid);
    }
}
