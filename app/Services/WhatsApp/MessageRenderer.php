<?php

namespace App\Services\WhatsApp;

use App\Models\Guest;
use App\Models\Invitation;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Fills {{variables}} in WhatsApp templates (rules.md section 40).
 */
class MessageRenderer
{
    public const MAX_LENGTH = 4000;

    /**
     * @return array<string, string> variable => human label
     */
    public static function variables(): array
    {
        return [
            'guest_name' => __('Guest name'),
            'event_name' => __('Event name'),
            'event_date' => __('Event date'),
            'event_time' => __('Event time'),
            'venue' => __('Venue'),
            'invitation_url' => __('Invitation link'),
            'rsvp_url' => __('RSVP link'),
        ];
    }

    public static function defaultTemplate(): string
    {
        return __("Dear {{guest_name}},\n\nWe would like to invite you to:\n\n*{{event_name}}*\n\nDate: {{event_date}}\nTime: {{event_time}}\nVenue: {{venue}}\n\nOpen your invitation:\n{{invitation_url}}\n\nThank you.");
    }

    /**
     * Event details shared by every recipient, computed once per campaign.
     *
     * @return array{event_name: string, event_date: string, event_time: string, venue: string}
     */
    public function eventContext(Invitation $invitation): array
    {
        $event = $invitation->sections()
            ->where('type', 'event')
            ->where('is_enabled', true)
            ->value('configuration')['events'][0] ?? [];

        $date = '';

        if (! empty($event['date'])) {
            try {
                $date = CarbonImmutable::parse($event['date'], $invitation->timezone)->translatedFormat('l, d F Y');
            } catch (Throwable) {
                $date = (string) $event['date'];
            }
        }

        return [
            'event_name' => $invitation->title,
            'event_date' => $date,
            'event_time' => trim(($event['start_time'] ?? '').(! empty($event['end_time']) ? ' – '.$event['end_time'] : '')),
            'venue' => (string) ($event['venue'] ?? ''),
        ];
    }

    /**
     * @param  array{event_name: string, event_date: string, event_time: string, venue: string}  $event
     */
    public function render(string $body, Guest $guest, Invitation $invitation, array $event): string
    {
        $invitationUrl = route('invitations.personal', ['slug' => $invitation->slug, 'token' => $guest->token]);

        $values = [
            ...$event,
            'guest_name' => $guest->name,
            'invitation_url' => $invitationUrl,
            'rsvp_url' => $invitationUrl.'#rsvp-form',
        ];

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $match): string => array_key_exists($match[1], $values) ? $values[$match[1]] : $match[0],
            $body,
        );

        return mb_substr(trim((string) $rendered), 0, self::MAX_LENGTH);
    }

    /**
     * Variables used in the body that the renderer does not know.
     *
     * @return array<int, string>
     */
    public function unknownVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $body, $matches);

        return array_values(array_unique(array_diff($matches[1], array_keys(self::variables()))));
    }
}
