<?php

namespace App\Services\Template;

use App\Enums\EventType;
use App\Enums\TemplateStatus;
use App\Models\InvitationTemplate;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Template export/import as a .zip (phase 9E): template.json (catalog data + configuration)
 * and the uploaded images it uses (thumbnail, cover photos, ornaments…), so a template can move
 * between installations or be backed up.
 *
 * Only files listed in template.json are read from the archive (no zip-slip), sizes and counts
 * are capped (no zip bombs), images are checked by their contents, the configuration goes
 * through TemplateSchema, and an imported template always starts as a draft.
 * Bundled files ("/illustrations/…", "/images/…") ship with the code and are not packed.
 */
class TemplatePackage
{
    public const FORMAT = 'invitefully-template';

    public const VERSION = 1;

    private const MANIFEST = 'template.json';

    private const MAX_MANIFEST_BYTES = 2 * 1024 * 1024;

    private const MAX_ASSET_BYTES = 10 * 1024 * 1024;

    private const MAX_TOTAL_BYTES = 100 * 1024 * 1024;

    private const MAX_ENTRIES = 400;

    private const IMAGE_TYPES = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];

    private const IMPORT_DIRECTORY = 'templates/imported';

    public function __construct(
        private TemplateSchema $schema,
        private TemplateService $templates,
        private AuditLogger $auditLogger,
    ) {}

    /**
     * Build the archive in a temporary file; the caller sends and deletes it.
     */
    public function export(InvitationTemplate $template, User $admin): string
    {
        $template->loadMissing('category');
        $definition = $template->definition();
        $disk = Storage::disk(MediaService::DISK);
        $assets = [];

        foreach ($this->storageUrls($definition) as $url) {
            $path = $this->diskPath($url);

            if ($path !== null && $disk->exists($path)) {
                $assets[$url] = 'assets/'.(count($assets) + 1).'.'.Str::lower(pathinfo($path, PATHINFO_EXTENSION));
            }
        }

        $thumbnail = $template->thumbnail && $disk->exists($template->thumbnail)
            ? 'assets/thumbnail.'.Str::lower(pathinfo($template->thumbnail, PATHINFO_EXTENSION))
            : null;

        $manifest = [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'template' => [
                'name' => $template->name,
                'slug' => $template->slug,
                'description' => $template->description,
                'is_premium' => (bool) $template->is_premium,
                'category' => $template->category ? [
                    'slug' => $template->category->slug,
                    'name' => $template->category->name,
                    'event_type' => $template->category->event_type?->value,
                ] : null,
                'definition' => $definition,
            ],
            'thumbnail' => $thumbnail,
            'assets' => $assets,
        ];

        $file = tempnam(sys_get_temp_dir(), 'template');
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::OVERWRITE);
        $zip->addFromString(self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        foreach ($assets as $url => $entry) {
            $zip->addFromString($entry, (string) $disk->get($this->diskPath($url)));
        }

        if ($thumbnail !== null) {
            $zip->addFromString($thumbnail, (string) $disk->get($template->thumbnail));
        }

        $zip->close();

        $this->auditLogger->record('template.exported', $admin, $template, ['slug' => $template->slug]);

        return $file;
    }

    public function fileName(InvitationTemplate $template): string
    {
        return 'template-'.$template->slug.'.zip';
    }

    /**
     * @throws ValidationException with a message under "package" when the archive is not a usable template.
     */
    public function import(string $path, User $admin): InvitationTemplate
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            $this->fail(__('The file is not a valid .zip archive.'));
        }

        try {
            $this->checkArchiveSize($zip);
            $manifest = $this->manifest($zip);
            $definition = $this->schema->validate($manifest['template']['definition'] ?? []);
            $files = $this->readAssets($zip, $manifest);
        } finally {
            $zip->close();
        }

        $stored = [];

        try {
            return DB::transaction(function () use ($manifest, $definition, $files, $admin, &$stored): InvitationTemplate {
                $directory = self::IMPORT_DIRECTORY.'/'.Str::lower((string) Str::ulid());
                $urls = [];

                foreach ($files['assets'] as $oldUrl => [$contents, $extension]) {
                    $stored[] = $path = $directory.'/'.Str::lower((string) Str::ulid()).'.'.$extension;
                    Storage::disk(MediaService::DISK)->put($path, $contents, 'public');
                    $urls[$oldUrl] = (string) parse_url(Storage::disk(MediaService::DISK)->url($path), PHP_URL_PATH);
                }

                $thumbnail = null;

                if ($files['thumbnail'] !== null) {
                    $stored[] = $thumbnail = $directory.'/thumbnail.'.$files['thumbnail'][1];
                    Storage::disk(MediaService::DISK)->put($thumbnail, $files['thumbnail'][0], 'public');
                }

                $data = $manifest['template'];
                $template = InvitationTemplate::query()->create([
                    ...$this->schema->validate($this->replaceUrls($definition, $urls)),
                    'template_category_id' => $this->category($data['category'] ?? null)->getKey(),
                    'name' => Str::limit(trim((string) ($data['name'] ?? '')) ?: __('Imported template'), 255, ''),
                    'slug' => $this->templates->uniqueSlug((string) ($data['slug'] ?? $data['name'] ?? 'template')),
                    'description' => is_string($data['description'] ?? null) ? Str::limit($data['description'], 1000, '') : null,
                    'is_premium' => (bool) ($data['is_premium'] ?? false),
                    'thumbnail' => $thumbnail,
                    'status' => TemplateStatus::Draft,
                    'sort_order' => (int) InvitationTemplate::query()->max('sort_order') + 1,
                ]);

                $this->auditLogger->record('template.imported', $admin, $template, ['slug' => $template->slug, 'assets' => count($urls)]);

                return $template;
            });
        } catch (\Throwable $exception) {
            Storage::disk(MediaService::DISK)->delete($stored);

            throw $exception;
        }
    }

    private function checkArchiveSize(ZipArchive $zip): void
    {
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $this->fail(__('The archive contains too many files.'));
        }

        $total = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $total += (int) ($zip->statIndex($index)['size'] ?? 0);
        }

        if ($total > self::MAX_TOTAL_BYTES) {
            $this->fail(__('The archive is too large once unpacked.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(ZipArchive $zip): array
    {
        $stat = $zip->statName(self::MANIFEST);

        if ($stat === false || $stat['size'] > self::MAX_MANIFEST_BYTES) {
            $this->fail(__('The archive has no template.json, so it is not an exported template.'));
        }

        $manifest = json_decode((string) $zip->getFromName(self::MANIFEST), true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT || ! is_array($manifest['template'] ?? null)) {
            $this->fail(__('The archive has no template.json, so it is not an exported template.'));
        }

        if ((int) ($manifest['version'] ?? 0) > self::VERSION) {
            $this->fail(__('This template was exported by a newer version of the app.'));
        }

        return $manifest;
    }

    /**
     * Read and check every listed file.
     *
     * @param  array<string, mixed>  $manifest
     * @return array{assets: array<string, array{0: string, 1: string}>, thumbnail: array{0: string, 1: string}|null}
     */
    private function readAssets(ZipArchive $zip, array $manifest): array
    {
        $assets = [];

        foreach ((array) ($manifest['assets'] ?? []) as $url => $entry) {
            if (! is_string($url) || $this->diskPath($url) === null || ! is_string($entry)) {
                $this->fail(__('The archive lists an invalid file.'));
            }

            $assets[$url] = $this->image($zip, $entry);
        }

        $thumbnail = is_string($manifest['thumbnail'] ?? null) ? $this->image($zip, $manifest['thumbnail']) : null;

        return ['assets' => $assets, 'thumbnail' => $thumbnail];
    }

    /**
     * @return array{0: string, 1: string} contents and the extension of its real type
     */
    private function image(ZipArchive $zip, string $entry): array
    {
        $stat = $zip->statName($entry);

        if (! preg_match('#^assets/[A-Za-z0-9_.-]+$#', $entry) || $stat === false || $stat['size'] > self::MAX_ASSET_BYTES) {
            $this->fail(__('The archive lists an invalid file.'));
        }

        $contents = (string) $zip->getFromName($entry);
        $info = @getimagesizefromstring($contents);
        $extension = is_array($info) ? (self::IMAGE_TYPES[$info[2]] ?? null) : null;

        if ($extension === null) {
            $this->fail(__('The archive contains a file that is not a PNG, JPG or WebP image.'));
        }

        return [$contents, $extension];
    }

    /**
     * The category with the exported slug, or a new inactive one, so the template is never orphaned.
     *
     * @param  array<string, mixed>|null  $data
     */
    private function category(?array $data): TemplateCategory
    {
        $slug = Str::slug((string) ($data['slug'] ?? '')) ?: 'imported';

        return TemplateCategory::query()->firstOrCreate(['slug' => $slug], [
            'name' => Str::limit(trim((string) ($data['name'] ?? '')) ?: Str::headline($slug), 100, ''),
            'event_type' => EventType::tryFrom((string) ($data['event_type'] ?? '')) ?? EventType::Other,
            'is_active' => false,
            'sort_order' => (int) TemplateCategory::query()->max('sort_order') + 1,
        ]);
    }

    /**
     * Every "/storage/…" URL in the configuration.
     *
     * @return array<int, string>
     */
    private function storageUrls(mixed $value): array
    {
        if (is_string($value)) {
            return $this->diskPath($value) !== null ? [$value] : [];
        }

        return is_array($value) ? array_values(array_unique(array_merge([], ...array_map(fn (mixed $item): array => $this->storageUrls($item), array_values($value))))) : [];
    }

    /**
     * @param  array<string, string>  $urls
     */
    private function replaceUrls(mixed $value, array $urls): mixed
    {
        if (is_string($value)) {
            return $urls[$value] ?? $value;
        }

        return is_array($value) ? array_map(fn (mixed $item): mixed => $this->replaceUrls($item, $urls), $value) : $value;
    }

    /**
     * "/storage/a/b.webp" → "a/b.webp" on the public disk; null for anything else (or path tricks).
     */
    private function diskPath(string $url): ?string
    {
        if (! preg_match('#^/storage/([A-Za-z0-9_\-/]+\.(?:webp|png|jpe?g))$#i', $url, $match) || str_contains($match[1], '..')) {
            return null;
        }

        return $match[1];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['package' => $message]);
    }
}
