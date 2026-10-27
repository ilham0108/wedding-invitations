<?php

namespace App\Services\Invitation;

use App\Models\Invitation;
use App\Services\Subscription\SubscriptionLimitService;
use Illuminate\Support\Str;

/**
 * Data for the downloadable Instagram Story image (1080×1920) of an invitation.
 * The image itself is drawn in the browser (resources/js/story.js) so the theme's web fonts apply.
 * The owner's choices (layout, custom text, guest button) live in invitation_settings.story;
 * guests download the same image from the invitation page when the owner allows it.
 */
class StoryCard
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    public const LAYOUTS = ['photo', 'minimal'];

    public const MAX_HEADLINE = 60;

    public const MAX_MESSAGE = 160;

    private const DEFAULTS = [
        'layout' => 'photo',
        'photo' => true,
        'venue' => true,
        'link' => true,
        // Empty: the cover subtitle is used.
        'headline' => '',
        'message' => '',
        'guest_button' => false,
    ];

    public function __construct(
        private InvitationRenderer $renderer,
        private SubscriptionLimitService $limits,
    ) {}

    /**
     * For the owner's story page.
     *
     * @return array{rendered: RenderedInvitation, card: array<string, mixed>, options: array<string, mixed>}
     */
    public function forInvitation(Invitation $invitation): array
    {
        $rendered = $this->renderer->forInvitation($invitation, isPreview: true);

        return [
            'rendered' => $rendered,
            'card' => $this->card($rendered, $invitation),
            'options' => $this->options($invitation->settings?->story),
        ];
    }

    /**
     * For the guest button on the invitation page; null when the owner has not turned it on.
     *
     * @return array{card: array<string, mixed>, options: array<string, mixed>}|null
     */
    public function forGuests(RenderedInvitation $rendered, Invitation $invitation): ?array
    {
        $options = $this->options($invitation->settings?->story);

        return $options['guest_button'] ? ['card' => $this->card($rendered, $invitation), 'options' => $options] : null;
    }

    /**
     * @param  array<string, mixed>|null  $story
     * @return array{layout: string, photo: bool, venue: bool, link: bool, headline: string, message: string, guest_button: bool}
     */
    public function options(?array $story): array
    {
        $story ??= [];

        return [
            'layout' => in_array($story['layout'] ?? null, self::LAYOUTS, true) ? $story['layout'] : self::DEFAULTS['layout'],
            'photo' => (bool) ($story['photo'] ?? self::DEFAULTS['photo']),
            'venue' => (bool) ($story['venue'] ?? self::DEFAULTS['venue']),
            'link' => (bool) ($story['link'] ?? self::DEFAULTS['link']),
            'headline' => $this->text(str_replace("\n", ' ', (string) ($story['headline'] ?? '')), self::MAX_HEADLINE),
            'message' => $this->text($story['message'] ?? '', self::MAX_MESSAGE),
            'guest_button' => (bool) ($story['guest_button'] ?? self::DEFAULTS['guest_button']),
        ];
    }

    /**
     * @param  array<string, mixed>  $story
     * @return array<string, mixed> what was stored
     */
    public function save(Invitation $invitation, array $story): array
    {
        $options = $this->options($story);
        $invitation->settings()->updateOrCreate([], ['story' => $options]);

        return $options;
    }

    /**
     * @return array<string, mixed>
     */
    private function card(RenderedInvitation $rendered, Invitation $invitation): array
    {
        $sections = collect($rendered->sections);
        $cover = $sections->firstWhere('type', 'cover')['config'] ?? [];
        $event = collect($sections->firstWhere('type', 'event')['config']['events'] ?? [])->first(fn (array $event): bool => filled($event['date_label'] ?? null));
        $theme = $rendered->theme;

        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'subtitle' => (string) ($cover['subtitle'] ?? ''),
            'names' => ($cover['title'] ?? '') ?: $invitation->title,
            'date' => filled($cover['date_text'] ?? null) ? $cover['date_text'] : (string) ($event['date_label'] ?? ''),
            'venue' => (string) ($event['venue'] ?? ''),
            'photo' => $cover['background_image'] ?? null,
            // Shown without the scheme, e.g. "invitefully.id/invitation/romeo-juliet". Never the personal link.
            'link' => Str::after(route('invitations.public', $invitation->slug), '://'),
            'branding' => $this->limits->showsBranding($invitation->user) ? __('Made with :app', ['app' => config('app.name')]) : null,
            'colors' => [
                'primary' => $theme['primary_color'],
                'secondary' => $theme['secondary_color'],
                'background' => $theme['background_color'],
                'surface' => $theme['surface_color'],
                'text' => $theme['text_color'],
            ],
            'fonts' => ['heading' => $theme['font_heading'], 'body' => $theme['font_body']],
            'file_name' => Str::slug($invitation->title ?: 'invitation').'-story.png',
        ];
    }

    private function text(mixed $value, int $max): string
    {
        // The message keeps the client's line breaks; the headline arrives as one line.
        return is_string($value) ? Str::limit(trim(str_replace("\r", '', $value)), $max, '') : '';
    }
}
