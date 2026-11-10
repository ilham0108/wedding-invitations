<?php

namespace App\Services\Rsvp;

use App\Models\Invitation;
use App\Models\InvitationTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Wishes sent from a preview (template preview, builder preview, the owner's invitation preview). They live in the
 * cache, never in the guestbook, and only for the visitor who sent them: the key hashes the session id with what is
 * previewed, so a stranger on a public template preview can never show text to anyone else. Bounded by MAX_MESSAGES
 * per visitor and preview and by TTL; the routes are rate limited and the form keeps its validation.
 */
class PreviewGuestbook
{
    public const MAX_MESSAGES = 10;

    /** Seconds a preview wish is kept. */
    public const TTL = 7200;

    public static function templateScope(InvitationTemplate $template): string
    {
        return 'template:'.$template->ulid;
    }

    public static function invitationScope(Invitation $invitation): string
    {
        return 'invitation:'.$invitation->ulid;
    }

    /**
     * The visitor's preview wishes, newest first, in the shape of GuestbookService::approvedForDisplay().
     *
     * @return array<int, array{name: string, message: string, date: string}>
     */
    public function messages(string $scope): array
    {
        $key = $this->key($scope);

        if ($key === null) {
            return [];
        }

        $stored = Cache::get($key);

        return collect(is_array($stored) ? $stored : [])
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['name'] ?? null) && is_string($item['message'] ?? null) && is_int($item['at'] ?? null))
            ->map(fn (array $item): array => ['name' => $item['name'], 'message' => $item['message'], 'date' => Carbon::createFromTimestamp($item['at'])->diffForHumans()])
            ->values()
            ->all();
    }

    /**
     * Keep a validated wish for this visitor. Plain arrays only: the cache never holds PHP objects.
     */
    public function add(string $scope, string $name, string $message): void
    {
        $key = $this->key($scope);

        if ($key === null) {
            return;
        }

        $stored = Cache::get($key);
        $items = [['name' => $name, 'message' => $message, 'at' => now()->getTimestamp()], ...(is_array($stored) ? $stored : [])];

        Cache::put($key, array_slice($items, 0, self::MAX_MESSAGES), self::TTL);
    }

    /**
     * Per visitor and preview; null without a session (nothing is shown or kept then).
     */
    private function key(string $scope): ?string
    {
        $request = request();

        if (! $request->hasSession() || $request->session()->getId() === '') {
            return null;
        }

        return 'preview-wishes:'.hash('sha256', $request->session()->getId().'|'.$scope);
    }
}
