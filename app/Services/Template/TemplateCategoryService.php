<?php

namespace App\Services\Template;

use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\AuditLogger;

/**
 * Admin template categories. A category with templates is deactivated instead of deleted.
 */
class TemplateCategoryService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, slug: string, event_type: string, description: ?string, icon: ?string, sort_order: int, is_active: bool}  $attributes
     */
    public function save(TemplateCategory $category, array $attributes, User $admin): TemplateCategory
    {
        $wasNew = ! $category->exists;

        $category->fill($attributes)->save();

        $this->auditLogger->record($wasNew ? 'template-category.created' : 'template-category.updated', $admin, $category, ['slug' => $category->slug]);

        return $category;
    }

    /**
     * @return bool true when deleted, false when it still has templates and was deactivated instead
     */
    public function remove(TemplateCategory $category, User $admin): bool
    {
        $inUse = InvitationTemplate::withTrashed()->where('template_category_id', $category->getKey())->exists();

        $inUse ? $category->update(['is_active' => false]) : $category->delete();

        $this->auditLogger->record($inUse ? 'template-category.deactivated' : 'template-category.deleted', $admin, $category, ['slug' => $category->slug]);

        return ! $inUse;
    }
}
