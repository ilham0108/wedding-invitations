<?php

namespace App\Services\Invitation;

use App\Models\Invitation;
use App\Models\InvitationSection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Section builder operations (rules.md section 16). Callers authorize the invitation first;
 * every lookup here is scoped to that invitation, so foreign section ULIDs resolve to 404.
 */
class InvitationSectionService
{
    public function __construct(private SectionRegistry $registry) {}

    public function find(Invitation $invitation, string $sectionUlid): InvitationSection
    {
        return $invitation->sections()->where('ulid', $sectionUlid)->firstOrFail();
    }

    public function add(Invitation $invitation, string $type): InvitationSection
    {
        $templateDefaults = $invitation->loadMissing('template')->template->default_configuration ?? [];

        return $invitation->sections()->create([
            'type' => $type,
            'variant' => $this->registry->resolveVariant($type, null),
            'sort_order' => (int) $invitation->sections()->max('sort_order') + 1,
            'is_enabled' => true,
            'configuration' => $this->registry->sanitize($type, $templateDefaults[$type] ?? []),
        ]);
    }

    public function duplicate(InvitationSection $section): InvitationSection
    {
        return DB::transaction(function () use ($section): InvitationSection {
            InvitationSection::query()
                ->where('invitation_id', $section->invitation_id)
                ->where('sort_order', '>', $section->sort_order)
                ->increment('sort_order');

            $copy = $section->replicate(['ulid']);
            $copy->sort_order = $section->sort_order + 1;
            $copy->save();

            return $copy;
        });
    }

    public function delete(InvitationSection $section): void
    {
        $section->delete();
    }

    public function toggle(InvitationSection $section): InvitationSection
    {
        $section->update(['is_enabled' => ! $section->is_enabled]);

        return $section;
    }

    /**
     * Move a section to a zero-based position and renumber the rest.
     */
    public function move(Invitation $invitation, string $sectionUlid, int $position): void
    {
        DB::transaction(function () use ($invitation, $sectionUlid, $position): void {
            /** @var Collection<int, InvitationSection> $sections */
            $sections = $invitation->sections()->lockForUpdate()->get(['id', 'ulid', 'sort_order']);
            $moving = $sections->firstWhere('ulid', $sectionUlid) ?? abort(404);

            $ordered = $sections->reject(fn (InvitationSection $section): bool => $section->is($moving))->values();
            $ordered->splice(max(0, min($position, $ordered->count())), 0, [$moving]);

            $ordered->each(function (InvitationSection $section, int $index): void {
                if ($section->sort_order !== $index) {
                    $section->update(['sort_order' => $index]);
                }
            });
        });
    }

    /**
     * @param  array<string, mixed>  $configuration  Already validated against SectionRegistry::rules().
     */
    public function updateConfiguration(InvitationSection $section, array $configuration, ?string $variant = null): InvitationSection
    {
        $section->update([
            'configuration' => $this->registry->sanitize($section->type, $configuration),
            'variant' => $this->registry->resolveVariant($section->type, $variant ?? $section->variant),
        ]);

        return $section;
    }
}
