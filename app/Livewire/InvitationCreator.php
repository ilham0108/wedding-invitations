<?php

namespace App\Livewire;

use App\Enums\EventType;
use App\Http\Requests\StoreInvitationRequest;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Services\Invitation\InvitationService;
use App\Services\Subscription\SubscriptionLimitService;
use App\Services\TemplateCatalog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Creation wizard (rules.md section 12): event type → template → basic info → create.
 */
class InvitationCreator extends Component
{
    public int $step = 1;

    public ?string $eventType = null;

    public ?string $templateSlug = null;

    public string $title = '';

    public ?string $eventDate = null;

    public ?string $eventTime = null;

    public string $venue = '';

    public string $timezone = 'Asia/Jakarta';

    public function mount(TemplateCatalog $catalog, ?string $preselectedTemplate = null): void
    {
        $this->timezone = Auth::user()->timezone;

        $template = $preselectedTemplate ? $catalog->findPublished($preselectedTemplate) : null;

        if ($template !== null) {
            $this->templateSlug = $template->slug;
            $this->eventType = $template->category->event_type->value;
            $this->step = 3;
        }
    }

    public function selectEventType(string $eventType): void
    {
        $this->eventType = EventType::tryFrom($eventType)?->value;
        $this->templateSlug = null;
        $this->step = $this->eventType ? 2 : 1;
    }

    public function selectTemplate(string $slug, TemplateCatalog $catalog): void
    {
        $this->templateSlug = $catalog->findPublished($slug)?->slug;
        $this->step = $this->templateSlug ? 3 : 2;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function create(InvitationService $invitations): void
    {
        Gate::authorize('create', Invitation::class);

        $request = new StoreInvitationRequest;
        $validated = Validator::make([
            'event_type' => $this->eventType,
            'template' => $this->templateSlug,
            'title' => trim($this->title),
            'event_date' => $this->eventDate ?: null,
            'event_time' => $this->eventTime ?: null,
            'venue' => trim($this->venue) ?: null,
            'timezone' => $this->timezone,
        ], $request->rules(), [], $request->attributes())->validate();

        $template = InvitationTemplate::query()->published()->where('slug', $validated['template'])->firstOrFail();
        $limits = app(SubscriptionLimitService::class);

        if (! $limits->canCreateInvitation(Auth::user())) {
            throw ValidationException::withMessages(['plan' => __('You have reached the invitation limit of your plan.')]);
        }

        if (! $limits->canUseTemplate(Auth::user(), $template)) {
            throw ValidationException::withMessages(['plan' => __('This template is only available on Premium.')]);
        }

        $invitation = $invitations->createFromTemplate(Auth::user(), $template, $validated);

        session()->flash('success', __('Invitation created. Start customizing it below.'));

        $this->redirectRoute('dashboard.invitations.edit', $invitation);
    }

    public function render(TemplateCatalog $catalog): View
    {
        $eventType = EventType::tryFrom((string) $this->eventType);
        $templates = $eventType ? $catalog->forEventType($eventType) : collect();

        return view('livewire.invitation-creator', [
            'eventTypes' => EventType::cases(),
            'templates' => $templates->isNotEmpty() ? $templates : $catalog->publishedTemplates(),
            'showingAllTemplates' => $templates->isEmpty(),
            'selectedTemplate' => $this->templateSlug ? $catalog->findPublished($this->templateSlug) : null,
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
