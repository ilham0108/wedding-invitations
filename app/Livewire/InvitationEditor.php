<?php

namespace App\Livewire;

use App\Livewire\Concerns\ResolvesEditableInvitation;
use App\Models\Invitation;
use App\Services\Invitation\InvitationService;
use App\Services\Invitation\ThemeResolver;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Editor shell (rules.md sections 17–19): section list, live preview, property/theme/settings panels.
 */
class InvitationEditor extends Component
{
    use ResolvesEditableInvitation;

    public ?string $selectedSection = null;

    public string $panel = 'section';

    /** @var array<string, string> */
    public array $theme = [];

    /** @var array{title: string, timezone: string, meta_title: ?string, meta_description: ?string, allow_search_indexing: bool} */
    public array $settings = [];

    public string $status = '';

    public ?string $savedAt = null;

    public function mount(Invitation $invitation, ThemeResolver $themes): void
    {
        Gate::authorize('update', $invitation);

        $invitation->loadMissing('settings');

        $this->invitationUlid = $invitation->ulid;
        $this->selectedSection = $invitation->sections()->value('ulid');
        $this->status = $invitation->status->value;
        $this->theme = $themes->resolve($invitation->settings?->template_theme ?? [], $invitation->settings?->theme ?? []);
        $this->settings = [
            'title' => $invitation->title,
            'timezone' => $invitation->timezone,
            'meta_title' => $invitation->settings?->meta_title,
            'meta_description' => $invitation->settings?->meta_description,
            'allow_search_indexing' => (bool) $invitation->settings?->allow_search_indexing,
        ];
    }

    #[On('section-selected')]
    public function selectSection(string $ulid): void
    {
        $this->selectedSection = $ulid;
        $this->panel = 'section';
    }

    #[On('section-removed')]
    public function sectionRemoved(?string $next = null): void
    {
        $this->selectedSection = $next;
    }

    public function updatedTheme(InvitationService $invitations, ThemeResolver $themes): void
    {
        abort_unless($this->canCustomizeTheme(), 403);

        $this->validate($themes->rules());

        $invitations->updateTheme($this->invitation(), $this->theme);
        $this->markSaved();
    }

    public function applyPreset(string $preset, ThemeResolver $themes, InvitationService $invitations): void
    {
        abort_unless($this->canCustomizeTheme(), 403);

        if (! isset($themes->presets()[$preset])) {
            return;
        }

        $this->theme = $themes->resolve(['preset' => $preset]);
        $invitations->updateTheme($this->invitation(), [...$this->theme, 'preset' => $preset]);
        $this->markSaved();
    }

    public function updatedSettings(InvitationService $invitations): void
    {
        $this->validate([
            'settings.title' => ['required', 'string', 'max:120'],
            'settings.timezone' => ['required', 'timezone:all'],
            'settings.meta_title' => ['nullable', 'string', 'max:120'],
            'settings.meta_description' => ['nullable', 'string', 'max:300'],
            'settings.allow_search_indexing' => ['boolean'],
        ]);

        $invitations->updateSettings($this->invitation(), $this->settings);
        $this->markSaved();
    }

    public function publish(InvitationService $invitations): void
    {
        $invitation = $invitations->publish($this->invitation(), Auth::user());
        $this->status = $invitation->status->value;
        $this->markSaved();
    }

    public function unpublish(InvitationService $invitations): void
    {
        $invitation = $invitations->unpublish($this->invitation(), Auth::user());
        $this->status = $invitation->status->value;
        $this->markSaved();
    }

    public function render(ThemeResolver $themes): View
    {
        $invitation = $this->invitation();
        $canCustomizeTheme = $this->canCustomizeTheme();

        if ($this->panel === 'theme' && ! $canCustomizeTheme) {
            $this->panel = 'section';
        }

        return view('livewire.invitation-editor', [
            'invitation' => $invitation,
            'canCustomizeTheme' => $canCustomizeTheme,
            'previewUrl' => route('dashboard.invitations.preview', ['invitation' => $invitation, 'embed' => 1]),
            'publicUrl' => route('invitations.public', $invitation->slug),
            'presets' => array_keys($themes->presets()),
            'fontsByCategory' => collect(config('invitation.fonts'))->map(fn (array $font, string $name): array => [...$font, 'name' => $name])->groupBy('category'),
            'themeOptions' => config('invitation.theme_options'),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    /**
     * The Theme panel follows the invitation owner's plan, so an admin editing it sees what the owner sees.
     */
    private function canCustomizeTheme(): bool
    {
        return app(SubscriptionLimitService::class)->canCustomizeTheme($this->invitation()->user);
    }

    private function markSaved(): void
    {
        $this->savedAt = now()->format('H:i:s');
        $this->dispatch('invitation-updated', section: $this->selectedSection);
    }
}
