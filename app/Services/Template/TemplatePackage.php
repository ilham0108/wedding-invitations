<?php

namespace App\Services\Template;

use App\Enums\EventType;
use App\Enums\IllustrationCategory;
use App\Enums\TemplateStatus;
use App\Models\Illustration;
use App\Models\InvitationTemplate;
use App\Models\MusicTrack;
use App\Models\TemplateCategory;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\MediaService;
use App\Support\AudioFormat;
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
 *
 * Library items the template points at by ULID travel in "library": the image under section
 * headings (decoration heading_ornament_image), the divider image (decoration divider_image) and
 * the default music (music.track). Uploaded ones are packed and become new library entries on
 * import; bundled ones are matched by their path.
 */
class TemplatePackage
{
    public const FORMAT = 'invitefully-template';

    /**
     * Ornament images in the decoration: library kind => [mode key, image key, fallback mode when the image is missing].
     */
    private const ORNAMENTS = [
        'heading_ornament' => ['heading_ornament', 'heading_ornament_image', 'design'],
        'divider' => ['divider', 'divider_image', 'line'],
    ];

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

        $library = $this->exportLibrary($definition);

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
            'library' => $library['manifest'],
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

        foreach ($library['files'] as $entry => $contents) {
            $zip->addFromString($entry, $contents);
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
            $libraryFiles = $this->readLibrary($zip, $manifest);
        } finally {
            $zip->close();
        }

        $stored = [];

        try {
            return DB::transaction(function () use ($manifest, $definition, $files, $libraryFiles, $admin, &$stored): InvitationTemplate {
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

                // The validated definition has lost library references unknown here; put the new ones in.
                $definition = $this->withLibrary($definition, $manifest['template']['definition'] ?? [], $this->importLibrary($libraryFiles, $stored));

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
        $extension = $this->imageExtension($contents);

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
     * Library items of the definition: manifest entries and the packed file contents (by archive entry).
     *
     * @param  array<string, mixed>  $definition
     * @return array{manifest: array<string, array<string, mixed>>, files: array<string, string>}
     */
    private function exportLibrary(array $definition): array
    {
        $manifest = [];
        $files = [];

        foreach (self::ORNAMENTS as $kind => [$modeKey, $imageKey]) {
            $ornamentUlid = ($definition['decoration'][$modeKey] ?? null) === 'image' ? ($definition['decoration'][$imageKey] ?? null) : null;
            $illustration = is_string($ornamentUlid) && $ornamentUlid !== '' ? Illustration::query()->where('ulid', $ornamentUlid)->first() : null;

            if ($illustration !== null) {
                $item = $this->libraryItem($illustration->disk, $illustration->path, str_replace('_', '-', $kind), $files);
                $manifest[$kind] = $item === null ? null : [...$item, 'name' => $illustration->name];
            }
        }

        $trackUlid = $definition['music']['track'] ?? null;
        $track = is_string($trackUlid) ? MusicTrack::query()->where('ulid', $trackUlid)->first() : null;

        if ($track !== null) {
            $item = $this->libraryItem($track->disk, $track->path, 'music', $files);
            $manifest['music'] = $item === null ? null : [...$item, 'title' => $track->title, 'artist' => $track->artist, 'is_premium' => (bool) $track->is_premium];
        }

        return ['manifest' => array_filter($manifest), 'files' => $files];
    }

    /**
     * A bundled file is referenced by its path; an uploaded one is packed under library/.
     *
     * @param  array<string, string>  $files
     * @return array{bundled?: string, entry?: string}|null null when the uploaded file is gone
     */
    private function libraryItem(?string $disk, string $path, string $name, array &$files): ?array
    {
        if ($disk === null) {
            return ['bundled' => ltrim($path, '/')];
        }

        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $entry = 'library/'.$name.'.'.Str::lower(pathinfo($path, PATHINFO_EXTENSION));
        $files[$entry] = (string) Storage::disk($disk)->get($path);

        return ['entry' => $entry];
    }

    /**
     * Read and check the packed library files: the heading ornament must be a PNG/JPG/WebP image,
     * the music an MP3 or audio-only M4A within the library upload limit.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, array<string, mixed>>
     */
    private function readLibrary(ZipArchive $zip, array $manifest): array
    {
        $library = [];

        foreach ([...array_keys(self::ORNAMENTS), 'music'] as $kind) {
            $item = $manifest['library'][$kind] ?? null;

            if (! is_array($item)) {
                continue;
            }

            if (is_string($item['bundled'] ?? null)) {
                $library[$kind] = [...$item, 'contents' => null];

                continue;
            }

            $entry = $item['entry'] ?? null;
            $stat = is_string($entry) ? $zip->statName($entry) : false;
            $limit = $kind === 'music' ? (int) config('invitation.music.max_library_upload_kb') * 1024 : self::MAX_ASSET_BYTES;

            if (! is_string($entry) || ! preg_match('#^library/[A-Za-z0-9_.-]+$#', $entry) || $stat === false || $stat['size'] > $limit) {
                $this->fail(__('The archive lists an invalid file.'));
            }

            $contents = (string) $zip->getFromName($entry);
            $extension = $kind === 'music' ? $this->audioExtension($contents) : $this->imageExtension($contents);

            if ($extension === null) {
                $this->fail($kind === 'music'
                    ? __('The archive contains music that is not an MP3 or M4A file.')
                    : __('The archive contains a file that is not a PNG, JPG or WebP image.'));
            }

            $library[$kind] = [...$item, 'contents' => $contents, 'extension' => $extension];
        }

        return $library;
    }

    /**
     * Create the library entries for packed files (or find the bundled ones) and return their ULIDs.
     *
     * @param  array<string, array<string, mixed>>  $library
     * @param  array<int, string>  $stored  paths written, for the cleanup when the import fails
     * @return array{heading_ornament: ?string, divider: ?string, music: ?string}
     */
    private function importLibrary(array $library, array &$stored): array
    {
        $disk = Storage::disk(MediaService::DISK);
        $ulids = ['heading_ornament' => null, 'divider' => null, 'music' => null];

        foreach (array_keys(self::ORNAMENTS) as $kind) {
            if ($item = $library[$kind] ?? null) {
                $ulids[$kind] = $this->importOrnament($item, $stored)?->ulid;
            }
        }

        if ($item = $library['music'] ?? null) {
            if ($item['contents'] === null) {
                $track = MusicTrack::query()->whereNull('disk')->where('path', $item['bundled'])->first();
            } elseif ($existing = $this->sameFile(MusicTrack::query()->whereNotNull('disk')->where('size', strlen($item['contents']))->get(), $item['contents'])) {
                $track = $existing;
            } else {
                $stored[] = $path = 'music/library/'.Str::lower((string) Str::ulid()).'.'.$item['extension'];
                $disk->put($path, $item['contents'], 'public');

                $track = MusicTrack::query()->create([
                    'title' => Str::limit(trim((string) ($item['title'] ?? '')) ?: __('Imported song'), 120, ''),
                    'artist' => Str::limit(trim((string) ($item['artist'] ?? '')), 120, '') ?: null,
                    'disk' => MediaService::DISK,
                    'path' => $path,
                    'size' => strlen($item['contents']),
                    'is_premium' => (bool) ($item['is_premium'] ?? false),
                    'sort_order' => (int) MusicTrack::query()->max('sort_order') + 1,
                ]);
            }

            $ulids['music'] = $track?->ulid;
        }

        return $ulids;
    }

    /**
     * The ornament illustration of a packed (or bundled) library item, created when this installation lacks it.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $stored
     */
    private function importOrnament(array $item, array &$stored): ?Illustration
    {
        if ($item['contents'] === null) {
            return Illustration::query()->whereNull('disk')->where('path', $item['bundled'])->first();
        }

        // Imported again on this installation: reuse the copy instead of filling the library with duplicates.
        if ($existing = $this->sameFile(Illustration::query()->where('category', IllustrationCategory::Ornament)->whereNotNull('disk')->get(), $item['contents'])) {
            return $existing;
        }

        $stored[] = $path = 'illustrations/'.IllustrationCategory::Ornament->value.'/'.Str::lower((string) Str::ulid()).'.'.$item['extension'];
        Storage::disk(MediaService::DISK)->put($path, $item['contents'], 'public');
        [$width, $height] = getimagesizefromstring($item['contents']) ?: [0, 0];

        return Illustration::query()->create([
            'category' => IllustrationCategory::Ornament,
            'name' => Str::limit(trim((string) ($item['name'] ?? '')) ?: __('Imported ornament'), 80, ''),
            'disk' => MediaService::DISK,
            'path' => $path,
            'width' => $width,
            'height' => $height,
            'is_premium' => false,
            'sort_order' => (int) Illustration::query()->where('category', IllustrationCategory::Ornament)->max('sort_order') + 1,
        ]);
    }

    /**
     * Point the definition at this installation's library entries. Without one, the heading
     * ornament and the divider fall back to the design's own and the template has no music.
     *
     * @param  array<string, mixed>  $definition  validated definition
     * @param  array<string, mixed>  $exported  definition as exported (keeps the music settings)
     * @param  array{heading_ornament: ?string, divider: ?string, music: ?string}  $ulids
     * @return array<string, mixed>
     */
    private function withLibrary(array $definition, array $exported, array $ulids): array
    {
        foreach (self::ORNAMENTS as $kind => [$modeKey, $imageKey, $fallback]) {
            if (($definition['decoration'][$modeKey] ?? null) === 'image') {
                $definition['decoration'] = $ulids[$kind] !== null
                    ? [...$definition['decoration'], $imageKey => $ulids[$kind]]
                    : [...$definition['decoration'], $modeKey => $fallback, $imageKey => ''];
            }
        }

        $definition['music'] = $ulids['music'] !== null && is_array($exported['music'] ?? null)
            ? [...$exported['music'], 'track' => $ulids['music']]
            : [];

        return $definition;
    }

    /**
     * The library entry (illustration or music track) whose stored file has exactly these contents.
     *
     * @template TModel of Illustration|MusicTrack
     *
     * @param  iterable<TModel>  $candidates
     * @return TModel|null
     */
    private function sameFile(iterable $candidates, string $contents): Illustration|MusicTrack|null
    {
        $size = strlen($contents);
        $hash = sha1($contents);

        foreach ($candidates as $candidate) {
            $disk = Storage::disk($candidate->disk);

            if ($disk->exists($candidate->path) && $disk->size($candidate->path) === $size && sha1((string) $disk->get($candidate->path)) === $hash) {
                return $candidate;
            }
        }

        return null;
    }

    private function imageExtension(string $contents): ?string
    {
        $info = @getimagesizefromstring($contents);

        return is_array($info) ? (self::IMAGE_TYPES[$info[2]] ?? null) : null;
    }

    /**
     * Judged by the contents, like library uploads (App\Support\AudioFormat).
     */
    private function audioExtension(string $contents): ?string
    {
        $file = tempnam(sys_get_temp_dir(), 'music');

        try {
            file_put_contents($file, $contents);

            return AudioFormat::detect($file, (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file));
        } finally {
            @unlink($file);
        }
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
