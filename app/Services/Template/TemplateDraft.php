<?php

namespace App\Services\Template;

use App\Models\InvitationTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * The template builder's working copy, kept per admin and template until it is saved
 * or discarded. Live templates therefore never change half-edited, while the builder
 * preview can still show unsaved changes.
 *
 * Only validated definition arrays are cached (the cache store refuses PHP objects).
 */
class TemplateDraft
{
    private const TTL_HOURS = 12;

    public function __construct(private TemplateSchema $schema) {}

    /**
     * The working definition: the unsaved draft, or the stored template when there is none.
     *
     * @return array<string, mixed>
     */
    public function definition(InvitationTemplate $template, User $admin): array
    {
        return Cache::get($this->key($template, $admin)) ?? $template->definition();
    }

    public function hasChanges(InvitationTemplate $template, User $admin): bool
    {
        return Cache::has($this->key($template, $admin));
    }

    /**
     * Apply a change to the working definition. The result is validated before it is kept.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function update(InvitationTemplate $template, User $admin, callable $change): array
    {
        $definition = $this->schema->validate($change($this->definition($template, $admin)));

        Cache::put($this->key($template, $admin), $definition, now()->addHours(self::TTL_HOURS));

        return $definition;
    }

    public function discard(InvitationTemplate $template, User $admin): void
    {
        Cache::forget($this->key($template, $admin));
    }

    /**
     * An unsaved copy of the template carrying the working definition, for previews.
     */
    public function previewTemplate(InvitationTemplate $template, User $admin): InvitationTemplate
    {
        return (clone $template)->forceFill($this->definition($template, $admin));
    }

    private function key(InvitationTemplate $template, User $admin): string
    {
        return "template-draft.{$template->ulid}.{$admin->getKey()}";
    }
}
