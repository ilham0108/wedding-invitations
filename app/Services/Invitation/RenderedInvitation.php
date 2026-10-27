<?php

namespace App\Services\Invitation;

use Carbon\CarbonImmutable;

/**
 * View-ready invitation produced by InvitationRenderer. Blade only reads from it.
 */
final readonly class RenderedInvitation
{
    /**
     * @param  array<string, string>  $theme
     * @param  array<string, string>  $decoration
     * @param  string  $design  Design pack key; $pageView is its page frame (null = classic page).
     * @param  array<int, string>  $assets  Extra Vite entries of the design.
     * @param  array<int, array{key: string, type: string, variant: string, view: string, native: bool, background: string, config: array<string, mixed>, ornaments: array<int, array<string, mixed>>}>  $sections
     * @param  array{title: string, description: string, image: ?string, canonical: ?string, indexable: bool}  $meta
     * @param  array{token?: ?string, rsvp?: array<string, mixed>, wishes?: array<string, mixed>}  $interaction  Form context for RSVP and wishes sections.
     * @param  array{url: string, start_at: int, loop: bool}|null  $music  Background music, null when off.
     */
    public function __construct(
        public string $title,
        public string $timezone,
        public array $theme,
        public string $cssVariables,
        public string $fontStylesheetUrl,
        public array $decoration,
        public string $design,
        public ?string $pageView,
        public array $assets,
        public array $sections,
        public array $meta,
        public ?CarbonImmutable $countdownTarget = null,
        public bool $isPreview = false,
        public ?string $guestName = null,
        public array $interaction = [],
        public ?array $music = null,
    ) {}
}
