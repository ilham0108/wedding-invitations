<?php

namespace App\Services;

use App\Enums\IllustrationCategory;
use App\Models\Illustration;
use App\Models\InvitationSection;
use App\Models\InvitationTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin-curated illustration library. Uploads become WebP like client photos;
 * bundled starter art lives in public/illustrations.
 */
class IllustrationService
{
    public function __construct(private MediaService $media) {}

    /**
     * @return Collection<int, Illustration>
     */
    public function forCategory(IllustrationCategory $category): Collection
    {
        return Illustration::query()->active()->where('category', $category)->ordered()->get();
    }

    public function store(UploadedFile $file, IllustrationCategory $category, ?string $name, bool $isPremium): Illustration
    {
        $stored = $this->media->storeWebp($file, 'illustrations/'.$category->value);

        return Illustration::query()->create([
            'category' => $category,
            'name' => Str::limit(trim((string) $name) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 80, ''),
            'disk' => MediaService::DISK,
            'path' => $stored['path'],
            'width' => $stored['width'],
            'height' => $stored['height'],
            'is_premium' => $isPremium,
            'sort_order' => (int) Illustration::query()->where('category', $category)->max('sort_order') + 1,
        ]);
    }

    /**
     * Whether any invitation or template still points at this illustration.
     * Searched by file name: JSON columns escape slashes, file names are unique.
     */
    public function isInUse(Illustration $illustration): bool
    {
        $needle = '%'.addcslashes(basename($illustration->path), '%_\\').'%';

        // Ornaments live in the template schema (per section) and in each invitation section's theme.
        return InvitationSection::query()
            ->where(fn ($query) => $query->where('configuration', 'like', $needle)->orWhere('theme', 'like', $needle))
            ->exists()
            || InvitationTemplate::withTrashed()
                ->where(fn ($query) => $query->where('default_configuration', 'like', $needle)
                    ->orWhere('sample_content', 'like', $needle)
                    ->orWhere('schema', 'like', $needle))
                ->exists();
    }

    /**
     * Delete when unused; otherwise only hide it from the picker so published invitations keep working.
     *
     * @return bool true when deleted, false when it was deactivated instead
     */
    public function remove(Illustration $illustration): bool
    {
        if ($this->isInUse($illustration)) {
            $illustration->update(['is_active' => false]);

            return false;
        }

        if (! $illustration->isBundled()) {
            Storage::disk($illustration->disk)->delete($illustration->path);
        }

        $illustration->delete();

        return true;
    }
}
