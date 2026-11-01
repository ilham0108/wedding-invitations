<?php

namespace App\Services;

use App\Models\Font;
use App\Models\InvitationSetting;
use App\Models\InvitationTemplate;
use App\Models\User;
use App\Support\FontFormat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Installs, hides and removes admin fonts (Font). Uploaded files go to the public disk under fonts/{ulid}/.
 */
class FontService
{
    /** Letters, digits, spaces and hyphens: the name is written into CSS as a family name. */
    public const NAME_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 \-]{0,59}$/';

    public const WEIGHTS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

    public const STYLES = ['normal', 'italic'];

    public function __construct(private AuditLogger $auditLogger) {}

    /**
     * @param  array{name: string, family: string, category: string}  $family  From BunnyFontCatalog::find().
     */
    public function addFromBunny(array $family, string $category, User $admin): Font
    {
        $font = Font::query()->create([
            'name' => $family['name'],
            'category' => $category,
            'source' => Font::SOURCE_BUNNY,
            'bunny_family' => $family['family'],
            'fallback' => FontRegistry::FALLBACKS[$category],
        ]);

        $this->auditLogger->record('font.created', $admin, $font, ['name' => $font->name, 'source' => Font::SOURCE_BUNNY]);

        return $font;
    }

    /**
     * Callers validate the files with App\Rules\FontFile first.
     *
     * @param  array<int, array{file: UploadedFile, weight: int, style: string}>  $files
     */
    public function addUpload(string $name, string $category, array $files, User $admin): Font
    {
        $font = DB::transaction(function () use ($name, $category, $files): Font {
            $font = Font::query()->create([
                'name' => $name,
                'category' => $category,
                'source' => Font::SOURCE_UPLOAD,
                'fallback' => FontRegistry::FALLBACKS[$category],
            ]);

            foreach ($files as $item) {
                $format = (string) FontFormat::detect((string) $item['file']->getRealPath());
                $path = $item['file']->storeAs(
                    'fonts/'.$font->ulid,
                    "{$item['weight']}-{$item['style']}-".Str::lower(Str::random(8)).'.'.FontFormat::EXTENSIONS[$format],
                    MediaService::DISK,
                );

                $font->files()->create([
                    'path' => $path,
                    'format' => $format,
                    'weight' => $item['weight'],
                    'style' => $item['style'],
                    'size' => (int) $item['file']->getSize(),
                ]);
            }

            return $font;
        });

        $this->auditLogger->record('font.created', $admin, $font, ['name' => $font->name, 'source' => Font::SOURCE_UPLOAD, 'files' => count($files)]);

        return $font;
    }

    public function setActive(Font $font, bool $active, User $admin): void
    {
        $font->update(['is_active' => $active]);
        $this->auditLogger->record($active ? 'font.shown' : 'font.hidden', $admin, $font, ['name' => $font->name]);
    }

    /**
     * Templates and invitations whose theme uses the font (heading or body).
     */
    public function usageCount(string $name): int
    {
        $templates = InvitationTemplate::query()
            ->where(fn ($query) => $query->where('theme->font_heading', $name)->orWhere('theme->font_body', $name))
            ->count();

        $invitations = InvitationSetting::query()
            ->where(fn ($query) => $query
                ->where('theme->font_heading', $name)->orWhere('theme->font_body', $name)
                ->orWhere('template_theme->font_heading', $name)->orWhere('template_theme->font_body', $name))
            ->count();

        return $templates + $invitations;
    }

    /**
     * Deletes the font and its files, unless a template or invitation uses it (then false: hide it instead).
     */
    public function delete(Font $font, User $admin): bool
    {
        if ($this->usageCount($font->name) > 0) {
            return false;
        }

        $directory = 'fonts/'.$font->ulid;
        $font->delete();
        Storage::disk(MediaService::DISK)->deleteDirectory($directory);

        $this->auditLogger->record('font.deleted', $admin, null, ['name' => $font->name]);

        return true;
    }
}
