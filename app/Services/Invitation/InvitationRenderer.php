<?php

namespace App\Services\Invitation;

use App\Models\Guest;
use App\Models\Invitation;
use App\Models\InvitationSection;
use App\Models\InvitationTemplate;
use App\Services\MusicService;
use App\Services\Rsvp\GuestbookService;
use App\Services\Rsvp\RsvpService;
use App\Support\SafeUrl;
use App\Support\VideoEmbed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns template configuration + invitation data into a RenderedInvitation.
 * The renderer never knows about specific templates: it only interprets config.
 */
class InvitationRenderer
{
    private const BACKGROUNDS = ['default', 'muted', 'primary'];

    private const PREVIEW_TIMEZONE = 'Asia/Jakarta';

    public function __construct(
        private SectionRegistry $registry,
        private ThemeResolver $themes,
        private DecorationResolver $decorations,
        private DesignRegistry $designs,
        private Ornaments $ornaments,
        private RsvpService $rsvps,
        private GuestbookService $guestbook,
        private MusicService $music,
    ) {}

    public function forInvitation(Invitation $invitation, bool $isPreview = false, ?Guest $guest = null): RenderedInvitation
    {
        $invitation->loadMissing([
            'settings',
            'sections' => fn ($query) => $query->where('is_enabled', true),
        ]);

        $settings = $invitation->settings;
        $design = $this->designs->resolve($settings?->design);

        $sections = $invitation->sections
            ->filter(fn (InvitationSection $section): bool => $section->is_enabled && $this->registry->has($section->type))
            ->map(fn (InvitationSection $section): array => $this->buildSection(
                key: $section->ulid,
                type: $section->type,
                variant: $section->variant,
                background: $section->theme['background'] ?? null,
                config: array_replace(
                    $this->registry->defaults($section->type),
                    $section->configuration ?? [],
                ),
                timezone: $invitation->timezone,
                design: $design,
                ornaments: $section->theme['ornaments'] ?? [],
                desktopOrnaments: $section->theme['desktop_ornaments'] ?? [],
            ))
            ->values()
            ->all();

        // Theme and decoration come from the copy taken at creation, never from the live template.
        $theme = $this->themes->resolve($settings?->template_theme ?? [], $settings?->theme ?? []);
        $cover = $this->firstConfig($sections, 'cover');

        return new RenderedInvitation(
            title: $invitation->title,
            timezone: $invitation->timezone,
            theme: $theme,
            cssVariables: $this->themes->cssVariables($theme),
            fontStylesheetUrl: $this->themes->fontStylesheetUrl($theme, $this->designs->fonts($design)),
            decoration: $this->decorations->resolve($settings?->decoration ?? []),
            design: $design,
            pageView: $this->designs->pageView($design),
            assets: $this->designs->assets($design),
            sections: $sections,
            meta: [
                'title' => $settings?->meta_title ?: $invitation->title,
                'description' => $settings?->meta_description ?: ($cover['subtitle'] ?? '') ?: __('You are invited to :title.', ['title' => $invitation->title]),
                'image' => $this->safeUrl($settings?->og_image ?: ($cover['background_image'] ?? null)),
                'canonical' => route('invitations.public', $invitation->slug),
                'indexable' => ! $isPreview && $invitation->isPublished() && (bool) $settings?->allow_search_indexing,
            ],
            countdownTarget: $this->countdownTarget($sections, $invitation->timezone),
            isPreview: $isPreview,
            guestName: $guest?->name,
            interaction: $this->interaction($invitation, $sections, $isPreview, $guest),
            music: $this->music->forPage($invitation),
        );
    }

    /**
     * Context for the RSVP and wishes forms. Forms have no action in preview mode.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array{token?: ?string, rsvp?: array<string, mixed>, wishes?: array<string, mixed>}
     */
    private function interaction(Invitation $invitation, array $sections, bool $isPreview, ?Guest $guest): array
    {
        $types = array_column($sections, 'type');
        $interaction = ['token' => $guest?->token];

        if (in_array('rsvp', $types, true)) {
            $config = $this->firstConfig($sections, 'rsvp');
            $existing = $guest?->rsvp()->first(['attendance', 'number_of_guests', 'meal_preference', 'message']);

            $interaction['rsvp'] = [
                'open' => $this->rsvps->isOpen($invitation, $config),
                'action' => $isPreview ? null : route('invitations.rsvp', $invitation->slug),
                'max_guests' => $this->rsvps->maxGuests($config, $guest),
                'meal_options' => $this->rsvps->mealOptions($config),
                'existing' => $existing ? [
                    'attendance' => $existing->attendance->value,
                    'attendance_label' => $existing->attendance->label(),
                    'number_of_guests' => $existing->number_of_guests,
                    'meal_preference' => $existing->meal_preference,
                    'message' => $existing->message,
                ] : null,
            ];
        }

        if (in_array('wishes', $types, true)) {
            $config = $this->firstConfig($sections, 'wishes');

            $interaction['wishes'] = [
                'open' => ! $invitation->hasExpired(),
                'action' => $isPreview ? null : route('invitations.wishes', $invitation->slug),
                'messages' => ($config['show_messages'] ?? true) ? $this->guestbook->approvedForDisplay($invitation) : [],
            ];
        }

        return $interaction;
    }

    /**
     * Render a template with its sample content, without touching any invitation.
     * Without samples it shows exactly what a new invitation starts with.
     */
    public function forTemplatePreview(InvitationTemplate $template, bool $withSamples = true): RenderedInvitation
    {
        $templateDefaults = $template->default_configuration ?? [];
        $design = $this->designs->resolve($template->design);
        $samples = $withSamples ? ($template->sample_content ?? []) : [];

        $sections = collect($template->sectionBlueprint())
            ->filter(fn (array $blueprint): bool => $this->registry->has($blueprint['type']))
            ->map(fn (array $blueprint, int $index): array => $this->buildSection(
                key: 'preview-'.$index,
                type: $blueprint['type'],
                variant: $blueprint['variant'],
                background: $blueprint['background'],
                config: array_replace(
                    $this->registry->defaults($blueprint['type']),
                    $templateDefaults[$blueprint['type']] ?? [],
                    $samples[$blueprint['type']] ?? [],
                ),
                timezone: self::PREVIEW_TIMEZONE,
                design: $design,
                ornaments: $blueprint['ornaments'],
                desktopOrnaments: $blueprint['desktop_ornaments'],
            ))
            ->values()
            ->all();

        $theme = $this->themes->resolve($template->theme ?? []);
        $title = $this->firstConfig($sections, 'cover')['title'] ?? $template->name;

        return new RenderedInvitation(
            title: $title ?: $template->name,
            timezone: self::PREVIEW_TIMEZONE,
            theme: $theme,
            cssVariables: $this->themes->cssVariables($theme),
            fontStylesheetUrl: $this->themes->fontStylesheetUrl($theme, $this->designs->fonts($design)),
            decoration: $this->decorations->resolve($template->decoration ?? []),
            design: $design,
            pageView: $this->designs->pageView($design),
            assets: $this->designs->assets($design),
            sections: $sections,
            meta: [
                'title' => $template->name.' · '.__('Template preview'),
                'description' => (string) $template->description,
                'image' => null,
                'canonical' => null,
                'indexable' => false,
            ],
            countdownTarget: $this->countdownTarget($sections, self::PREVIEW_TIMEZONE),
            isPreview: true,
            interaction: [
                'token' => null,
                'rsvp' => ['open' => true, 'action' => null, 'max_guests' => 5, 'meal_options' => [], 'existing' => null],
                'wishes' => ['open' => true, 'action' => null, 'messages' => []],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{key: string, type: string, variant: string, view: string, native: bool, background: string, config: array<string, mixed>, ornaments: array<int, array<string, mixed>>, desktop_ornaments: array<int, array<string, mixed>>}
     */
    private function buildSection(string $key, string $type, ?string $variant, ?string $background, array $config, string $timezone, string $design, mixed $ornaments = [], mixed $desktopOrnaments = []): array
    {
        $variant = $this->registry->resolveVariant($type, $variant);
        $view = $this->designs->sectionView($design, $type, $variant);

        return [
            'key' => $key,
            'type' => $type,
            'variant' => $variant,
            'view' => $view['view'],
            'native' => $view['native'],
            'background' => in_array($background, self::BACKGROUNDS, true) ? $background : 'default',
            'config' => $this->normalizeConfig($type, $config, $timezone),
            'ornaments' => $this->ornaments->clean($ornaments),
            // Only the cover uses these, on the desktop side panel of designs that have one.
            'desktop_ornaments' => $this->ornaments->clean($desktopOrnaments),
        ];
    }

    /**
     * Strip unsafe URLs and derive presentation values (embeds, phone links).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalizeConfig(string $type, array $config, string $timezone): array
    {
        if (array_key_exists('background_photo', $config)) {
            $config['background_photo'] = $this->safeUrl($config['background_photo']);
            $config['background_overlay'] = in_array($config['background_overlay'] ?? null, ['strong', 'medium', 'light', 'none'], true) ? $config['background_overlay'] : 'strong';
        }

        return match ($type) {
            'cover' => [
                ...$config,
                'background_image' => $this->safeUrl($config['background_image'] ?? null),
                'desktop_image' => $this->safeUrl($config['desktop_image'] ?? null),
                'desktop_text_position' => in_array($config['desktop_text_position'] ?? null, ['top', 'center', 'bottom'], true) ? $config['desktop_text_position'] : 'center',
                'background_motion' => in_array($config['background_motion'] ?? null, ['none', 'zoom-out', 'drift'], true) ? $config['background_motion'] : 'none',
                'frame_style' => in_array($config['frame_style'] ?? null, ['blur', 'transparent', 'solid'], true) ? $config['frame_style'] : 'blur',
            ],
            'couple' => [
                ...$config,
                'photo_shape' => in_array($config['photo_shape'] ?? null, ['default', 'rounded', 'circle', 'oval', 'arch'], true) ? $config['photo_shape'] : 'default',
                'photo_frame' => $this->safeUrl($config['photo_frame'] ?? null),
                'photo_frame_inset' => is_numeric($config['photo_frame_inset'] ?? null) ? max(0.0, min(40.0, (float) $config['photo_frame_inset'])) : 12.0,
                'people' => $this->mapList($config['people'] ?? [], fn (array $person): array => [
                    ...$person,
                    'photo' => $this->safeUrl($person['photo'] ?? null),
                    'socials' => $this->socialLinks($person),
                ]),
            ],
            'story' => [...$config, 'entries' => $this->mapList($config['entries'] ?? [], fn (array $entry): array => [...$entry, 'image' => $this->safeUrl($entry['image'] ?? null)])],
            'gallery' => [...$config, 'images' => array_values(array_filter(array_map(fn (mixed $url): ?string => $this->safeUrl($url), Arr::wrap($config['images'] ?? []))))],
            // The invitation video section only embeds YouTube and Vimeo players.
            'video' => [...$config, ...$this->streamingEmbed((string) ($config['url'] ?? ''))],
            'maps' => [...$config, 'maps_url' => $this->safeUrl($config['maps_url'] ?? null), 'embed_url' => $this->mapsEmbedUrl($config)],
            'contact' => [...$config, 'contacts' => $this->mapList($config['contacts'] ?? [], fn (array $contact): array => [...$contact, 'whatsapp' => $this->whatsAppNumber($contact['phone'] ?? null)])],
            'dress_code' => [
                ...$config,
                'colors' => array_values(array_filter(Arr::wrap($config['colors'] ?? []), fn (mixed $color): bool => is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1)),
                'notes' => $this->lines($config['notes'] ?? ''),
            ],
            'gift' => [...$config, 'wishlist' => $this->mapList($config['wishlist'] ?? [], fn (array $item): array => [...$item, 'url' => $this->safeUrl($item['url'] ?? null)])],
            'closing' => [...$config, 'inviters' => $this->lines($config['inviters'] ?? '')],
            'health_protocol' => [...$config, 'items' => $this->mapList($config['items'] ?? [], fn (array $item): array => [
                ...$item,
                'icon_class' => config('invitation.health_protocol_icons')[$item['icon'] ?? ''] ?? 'fa-solid fa-circle-check',
            ])],
            'event' => [...$config, 'events' => $this->mapList($config['events'] ?? [], fn (array $event): array => [
                ...$event,
                'maps_url' => $this->safeUrl($event['maps_url'] ?? null),
                'date_label' => $this->dateLabel($event['date'] ?? null),
                'calendar_url' => ! empty($event['add_to_calendar']) ? $this->calendarUrl($event, $timezone) : null,
            ])],
            'rsvp' => [...$config, 'deadline_label' => $this->dateLabel($config['deadline'] ?? null)],
            default => $config,
        };
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array<int, array<string, mixed>>
     */
    private function mapList(mixed $items, callable $callback): array
    {
        return collect(Arr::wrap($items))->filter(fn (mixed $item): bool => is_array($item))->map($callback)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $person
     * @return array<string, string> network => safe URL, in display order
     */
    private function socialLinks(array $person): array
    {
        return collect(['instagram', 'tiktok', 'facebook', 'x', 'youtube'])
            ->mapWithKeys(fn (string $network): array => [$network => $this->safeUrl($person[$network] ?? null)])
            ->filter()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function lines(mixed $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text) ?: [])));
    }

    /**
     * Google Calendar "add event" link. Timed events are converted to UTC from the invitation
     * timezone; events without a start time become all-day events.
     *
     * @param  array<string, mixed>  $event
     */
    private function calendarUrl(array $event, string $timezone): ?string
    {
        if (empty($event['date'])) {
            return null;
        }

        try {
            if (filled($event['start_time'] ?? null)) {
                $start = CarbonImmutable::parse($event['date'].' '.$event['start_time'], $timezone)->utc();
                $end = filled($event['end_time'] ?? null)
                    ? CarbonImmutable::parse($event['date'].' '.$event['end_time'], $timezone)->utc()
                    : $start->addHours(2);
                $end = $end->greaterThan($start) ? $end : $start->addHours(2);
                $dates = $start->format('Ymd\THis\Z').'/'.$end->format('Ymd\THis\Z');
            } else {
                $day = CarbonImmutable::parse($event['date']);
                $dates = $day->format('Ymd').'/'.$day->addDay()->format('Ymd');
            }
        } catch (Throwable) {
            return null;
        }

        return 'https://calendar.google.com/calendar/render?'.http_build_query([
            'action' => 'TEMPLATE',
            'text' => (string) ($event['title'] ?? ''),
            'dates' => $dates,
            'location' => trim(($event['venue'] ?? '').' '.($event['address'] ?? '')),
            'details' => (string) ($event['description'] ?? ''),
        ]);
    }

    /**
     * Only http(s) URLs and root-relative paths may reach src/href attributes.
     */
    private function safeUrl(mixed $url): ?string
    {
        return SafeUrl::filter($url);
    }

    /**
     * @return array{provider: ?string, embed_url: ?string, thumbnail: ?string}
     */
    private function streamingEmbed(string $url): array
    {
        $embed = VideoEmbed::parse($url);

        return in_array($embed['provider'], ['youtube', 'vimeo'], true)
            ? $embed
            : ['provider' => null, 'embed_url' => null, 'thumbnail' => null];
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function mapsEmbedUrl(array $config): ?string
    {
        if (! is_numeric($config['latitude'] ?? null) || ! is_numeric($config['longitude'] ?? null)) {
            return null;
        }

        return sprintf('https://www.google.com/maps?q=%F,%F&z=15&output=embed', (float) $config['latitude'], (float) $config['longitude']);
    }

    private function dateLabel(mixed $date): ?string
    {
        if (! is_string($date) || $date === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->translatedFormat('l, d F Y');
        } catch (Throwable) {
            return null;
        }
    }

    private function whatsAppNumber(mixed $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        return Str::startsWith($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function firstConfig(array $sections, string $type): array
    {
        return collect($sections)->firstWhere('type', $type)['config'] ?? [];
    }

    /**
     * The countdown targets the earliest dated event, interpreted in the invitation timezone.
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function countdownTarget(array $sections, string $timezone): ?CarbonImmutable
    {
        $events = $this->firstConfig($sections, 'event')['events'] ?? [];

        return collect($events)
            ->filter(fn (array $event): bool => ! empty($event['date']))
            ->map(function (array $event) use ($timezone): ?CarbonImmutable {
                try {
                    return CarbonImmutable::parse(trim($event['date'].' '.($event['start_time'] ?? '00:00')), $timezone);
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->sort()
            ->first();
    }
}
