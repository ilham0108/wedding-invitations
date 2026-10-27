<?php

namespace App\Services\Invitation;

use App\Enums\EventType;
use App\Enums\InvitationStatus;
use App\Events\InvitationPublished;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Subscription\SubscriptionLimitService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function __construct(
        private SectionRegistry $registry,
        private ThemeResolver $themes,
        private AuditLogger $auditLogger,
        private Ornaments $ornaments,
    ) {}

    /**
     * Create a draft invitation whose sections are copied from the template blueprint.
     * Template defaults, design, theme and decoration are copied once; afterwards the invitation
     * owns its data, so later template edits never change existing invitations.
     *
     * @param  array{title: string, event_type: string, timezone: string, event_date?: ?string, event_time?: ?string, venue?: ?string}  $data
     */
    public function createFromTemplate(User $user, InvitationTemplate $template, array $data): Invitation
    {
        $invitation = DB::transaction(function () use ($user, $template, $data): Invitation {
            $invitation = $user->invitations()->create([
                'invitation_template_id' => $template->getKey(),
                'title' => $data['title'],
                'slug' => $this->uniqueSlug($data['title']),
                'status' => InvitationStatus::Draft,
                'event_type' => $data['event_type'],
                'timezone' => $data['timezone'],
            ]);

            $invitation->settings()->create([
                'allow_search_indexing' => false,
                'design' => $template->design ?? DesignRegistry::DEFAULT,
                'template_theme' => $template->theme ?? [],
                'decoration' => $template->decoration ?? [],
            ]);

            $templateDefaults = $template->default_configuration ?? [];
            $sortOrder = 0;

            foreach ($template->sectionBlueprint() as $blueprint) {
                if (! $this->registry->has($blueprint['type'])) {
                    continue;
                }

                $invitation->sections()->create([
                    'type' => $blueprint['type'],
                    'variant' => $this->registry->resolveVariant($blueprint['type'], $blueprint['variant']),
                    'sort_order' => $sortOrder++,
                    'is_enabled' => true,
                    'configuration' => array_replace(
                        $this->registry->defaults($blueprint['type']),
                        $templateDefaults[$blueprint['type']] ?? [],
                        $this->prefill($blueprint['type'], $data),
                    ),
                    // Section presentation: its background and the template's ornaments for it.
                    'theme' => array_filter([
                        'background' => in_array($blueprint['background'], [null, 'default'], true) ? null : $blueprint['background'],
                        'ornaments' => $this->ornaments->clean($blueprint['ornaments']) ?: null,
                        'desktop_ornaments' => $this->ornaments->clean($blueprint['desktop_ornaments']) ?: null,
                    ]) ?: null,
                ]);
            }

            return $invitation;
        });

        $this->auditLogger->record('invitation.created', $user, $invitation, [
            'template' => $template->slug,
        ]);

        return $invitation;
    }

    /**
     * @throws ValidationException when nothing would be visible to guests.
     */
    public function publish(Invitation $invitation, User $user): Invitation
    {
        if (! $invitation->sections()->where('is_enabled', true)->exists()) {
            throw ValidationException::withMessages([
                'publish' => __('Invitation could not be published. Enable at least one section first.'),
            ]);
        }

        if (! app(SubscriptionLimitService::class)->isInvitationActive($invitation)) {
            throw ValidationException::withMessages([
                'publish' => __('Your current plan does not cover this invitation. Upgrade or renew to publish it.'),
            ]);
        }

        $invitation->forceFill([
            'status' => InvitationStatus::Published,
            'published_at' => $invitation->published_at ?? now(),
        ])->save();

        InvitationPublished::dispatch($invitation, $user);

        return $invitation;
    }

    public function unpublish(Invitation $invitation, User $user): Invitation
    {
        $invitation->forceFill(['status' => InvitationStatus::Draft])->save();

        $this->auditLogger->record('invitation.unpublished', $user, $invitation);

        return $invitation;
    }

    /**
     * Store only theme keys that pass ThemeResolver validation, plus a known preset.
     *
     * @param  array<string, mixed>  $theme
     */
    public function updateTheme(Invitation $invitation, array $theme): void
    {
        $clean = collect($theme)
            ->filter(fn (mixed $value, string $key): bool => $key === 'preset'
                ? is_string($value) && isset($this->themes->presets()[$value])
                : $this->themes->isValid($key, $value))
            ->all();

        $invitation->settings()->updateOrCreate([], ['theme' => $clean]);
    }

    /**
     * @param  array{title: string, timezone: string, meta_title: ?string, meta_description: ?string, allow_search_indexing: bool}  $settings
     */
    public function updateSettings(Invitation $invitation, array $settings): void
    {
        DB::transaction(function () use ($invitation, $settings): void {
            $invitation->update([
                'title' => $settings['title'],
                'timezone' => $settings['timezone'],
            ]);

            $invitation->settings()->updateOrCreate([], [
                'meta_title' => $settings['meta_title'] ?: null,
                'meta_description' => $settings['meta_description'] ?: null,
                'allow_search_indexing' => $settings['allow_search_indexing'],
            ]);
        });
    }

    /**
     * Unique, human-readable slug. Soft-deleted invitations keep their slug reserved.
     */
    public function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 100, '') ?: 'invitation';
        $slug = $base;

        while (Invitation::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }

    /**
     * Map the wizard's basic information onto the sections that display it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prefill(string $type, array $data): array
    {
        $date = ! empty($data['event_date']) ? CarbonImmutable::parse($data['event_date']) : null;
        $eventType = EventType::tryFrom((string) ($data['event_type'] ?? ''));

        return match ($type) {
            'cover' => [
                'title' => $data['title'],
                'subtitle' => $eventType?->label() ?? '',
                'date_text' => $date?->translatedFormat('l, d F Y') ?? '',
            ],
            'event' => $date ? [
                'events' => [[
                    'title' => $eventType?->label() ?? __('Main Event'),
                    'date' => $date->toDateString(),
                    'start_time' => $data['event_time'] ?? null,
                    'end_time' => null,
                    'venue' => $data['venue'] ?? '',
                    'address' => '',
                    'description' => '',
                ]],
            ] : [],
            'maps' => ['venue' => $data['venue'] ?? ''],
            default => [],
        };
    }
}
