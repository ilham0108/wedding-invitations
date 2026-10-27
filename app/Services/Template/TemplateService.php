<?php

namespace App\Services\Template;

use App\Enums\TemplateStatus;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Admin template management (rules.md section 50). The template's configuration is
 * edited by the builder; this service handles catalog data and the template lifecycle.
 */
class TemplateService
{
    /**
     * Starting structure for a template created from scratch.
     */
    private const STARTER = [
        'schema' => ['sections' => ['cover', 'couple', 'event', 'rsvp', 'closing']],
        'theme' => ['preset' => 'elegant'],
    ];

    private const THUMBNAIL_DIRECTORY = 'templates/thumbnails';

    public function __construct(
        private TemplateSchema $schema,
        private MediaService $media,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * New templates start as drafts so they never reach clients half-configured.
     *
     * @param  array{name: string, slug: string, template_category_id: int, description: ?string, is_premium: bool, sort_order: int}  $attributes
     */
    public function create(array $attributes, User $admin): InvitationTemplate
    {
        $template = InvitationTemplate::query()->create([
            ...$attributes,
            ...$this->schema->validate(self::STARTER),
            'status' => TemplateStatus::Draft,
        ]);

        $this->auditLogger->record('template.created', $admin, $template, ['slug' => $template->slug]);

        return $template;
    }

    /**
     * @param  array{name: string, slug: string, template_category_id: int, description: ?string, is_premium: bool, sort_order: int}  $attributes
     */
    public function update(InvitationTemplate $template, array $attributes, User $admin): InvitationTemplate
    {
        $template->update($attributes);

        $this->auditLogger->record('template.updated', $admin, $template, ['slug' => $template->slug]);

        return $template;
    }

    /**
     * Replace the template's configuration (from the builder). Existing invitations keep their copy.
     *
     * @param  array<string, mixed>  $definition
     *
     * @throws ValidationException
     */
    public function updateDefinition(InvitationTemplate $template, array $definition, User $admin): InvitationTemplate
    {
        $template->update($this->schema->validate($definition));

        $this->auditLogger->record('template.configured', $admin, $template, [
            'slug' => $template->slug,
            'sections' => array_column($template->schema['sections'], 'type'),
        ]);

        return $template;
    }

    /**
     * Copy everything, including the thumbnail file, into a new draft.
     */
    public function duplicate(InvitationTemplate $template, User $admin): InvitationTemplate
    {
        $copy = $template->replicate(['thumbnail', 'preview_image']);
        $copy->fill([
            'name' => Str::limit(__(':name (copy)', ['name' => $template->name]), 255, ''),
            'slug' => $this->uniqueSlug($template->slug.'-copy'),
            'status' => TemplateStatus::Draft,
            'sort_order' => (int) InvitationTemplate::query()->max('sort_order') + 1,
            'thumbnail' => $this->copyThumbnail($template),
        ])->save();

        $this->auditLogger->record('template.duplicated', $admin, $copy, ['source' => $template->slug, 'slug' => $copy->slug]);

        return $copy;
    }

    /**
     * @throws ValidationException when the stored configuration is not a valid template.
     */
    public function publish(InvitationTemplate $template, User $admin): InvitationTemplate
    {
        try {
            $this->schema->validate($template->definition());
        } catch (ValidationException) {
            throw ValidationException::withMessages([
                'publish' => __('This template has configuration errors. Fix them in the builder before publishing.'),
            ]);
        }

        $template->update(['status' => TemplateStatus::Published]);

        $this->auditLogger->record('template.published', $admin, $template, ['slug' => $template->slug]);

        return $template;
    }

    public function unpublish(InvitationTemplate $template, User $admin): InvitationTemplate
    {
        $template->update(['status' => TemplateStatus::Draft]);

        $this->auditLogger->record('template.unpublished', $admin, $template, ['slug' => $template->slug]);

        return $template;
    }

    /**
     * Templates that invitations were created from are only unpublished, never deleted.
     *
     * @return bool true when deleted, false when it was unpublished instead
     */
    public function remove(InvitationTemplate $template, User $admin): bool
    {
        if ($this->isInUse($template)) {
            $template->update(['status' => TemplateStatus::Draft]);
            $this->auditLogger->record('template.unpublished', $admin, $template, ['slug' => $template->slug]);

            return false;
        }

        $template->delete();
        $this->auditLogger->record('template.deleted', $admin, $template, ['slug' => $template->slug]);

        return true;
    }

    public function isInUse(InvitationTemplate $template): bool
    {
        return Invitation::withTrashed()->where('invitation_template_id', $template->getKey())->exists();
    }

    /**
     * Callers validate the upload with MediaService::imageRules() first.
     */
    public function replaceThumbnail(InvitationTemplate $template, UploadedFile $file): void
    {
        $stored = $this->media->storeWebp($file, self::THUMBNAIL_DIRECTORY);

        $this->deleteThumbnailFile($template);
        $template->update(['thumbnail' => $stored['path']]);
    }

    public function removeThumbnail(InvitationTemplate $template): void
    {
        $this->deleteThumbnailFile($template);
        $template->update(['thumbnail' => null]);
    }

    /**
     * Unique slug; soft-deleted templates keep their slug reserved.
     */
    public function uniqueSlug(string $value, ?InvitationTemplate $ignore = null): string
    {
        $base = Str::limit(Str::slug($value), 90, '') ?: 'template';
        $slug = $base;

        while (InvitationTemplate::withTrashed()->where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private function copyThumbnail(InvitationTemplate $template): ?string
    {
        $disk = Storage::disk(MediaService::DISK);

        if (! $template->thumbnail || ! $disk->exists($template->thumbnail)) {
            return null;
        }

        $path = self::THUMBNAIL_DIRECTORY.'/'.Str::lower((string) Str::ulid()).'.webp';
        $disk->copy($template->thumbnail, $path);

        return $path;
    }

    private function deleteThumbnailFile(InvitationTemplate $template): void
    {
        if ($template->thumbnail) {
            Storage::disk(MediaService::DISK)->delete($template->thumbnail);
        }
    }
}
