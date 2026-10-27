<?php

namespace App\Services;

use App\Models\SiteContent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Marketing site content (Admin CMS, rules.md section 52). Sections and their defaults are
 * defined in config/site-content.php; saved values are cached as a plain array.
 */
class SiteContentService
{
    private const CACHE_KEY = 'site_content.v1';

    private const IMAGE_DIRECTORY = 'cms';

    public function __construct(private MediaService $media, private AuditLogger $auditLogger) {}

    /**
     * @return array<string, string> section key => label
     */
    public function sections(): array
    {
        return array_map(fn (array $section): string => $section['label'], config('site-content.sections'));
    }

    /**
     * Every section with its defaults filled in.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => SiteContent::query()
            ->get(['section', 'content'])
            ->mapWithKeys(fn (SiteContent $content): array => [$content->section => (array) $content->content])
            ->all());

        $content = [];

        foreach (config('site-content.sections') as $key => $section) {
            $defaults = $section['defaults'];
            $content[$key] = [...$defaults, ...array_intersect_key($stored[$key] ?? [], $defaults)];
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    public function section(string $key): array
    {
        return $this->all()[$key] ?? [];
    }

    /**
     * Validation rules for a section's form, keyed relative to the section.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(string $key): array
    {
        $icon = ['required', Rule::in(config('site-content.icons'))];
        $max = config('site-content.max_items');
        $intro = [
            'eyebrow' => ['nullable', 'string', 'max:60'],
            'heading' => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:300'],
        ];
        $iconItems = fn (string $section, bool $described): array => [
            'items' => ['array', 'max:'.$max[$section]],
            'items.*.icon' => $icon,
            'items.*.title' => ['required', 'string', 'max:80'],
            ...($described ? ['items.*.description' => ['nullable', 'string', 'max:200']] : []),
        ];

        return match ($key) {
            'hero' => [
                'badge' => ['nullable', 'string', 'max:60'],
                'title' => ['required', 'string', 'max:120'],
                'subtitle' => ['nullable', 'string', 'max:300'],
                'secondary_label' => ['nullable', 'string', 'max:40'],
                'primary_label' => ['required', 'string', 'max:40'],
                ...$iconItems('hero', false),
            ],
            'templates' => [...$intro, 'link_label' => ['nullable', 'string', 'max:40']],
            'steps' => [...$intro, ...$iconItems('steps', true)],
            'features' => [...$intro, ...$iconItems('features', true)],
            'pricing' => $intro,
            'faq' => [
                'eyebrow' => $intro['eyebrow'],
                'heading' => $intro['heading'],
                'items' => ['array', 'max:'.$max['faq']],
                'items.*.question' => ['required', 'string', 'max:200'],
                'items.*.answer' => ['required', 'string', 'max:2000'],
            ],
            'cta' => [
                'eyebrow' => $intro['eyebrow'],
                'heading' => $intro['heading'],
                'text' => ['nullable', 'string', 'max:300'],
                'button_label' => ['required', 'string', 'max:40'],
            ],
            'contact' => [
                'tagline' => ['nullable', 'string', 'max:200'],
                'email' => ['nullable', 'email', 'max:120'],
                'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{8,20}$/'],
                'address' => ['nullable', 'string', 'max:300'],
                'instagram' => ['nullable', 'url:https', 'max:200'],
                'facebook' => ['nullable', 'url:https', 'max:200'],
                'tiktok' => ['nullable', 'url:https', 'max:200'],
                'youtube' => ['nullable', 'url:https', 'max:200'],
            ],
            default => [],
        };
    }

    /**
     * Store a validated section. Unknown keys are dropped; the section's photo is kept (see replaceImage()).
     *
     * @param  array<string, mixed>  $values
     */
    public function save(string $key, array $values, User $admin): void
    {
        $defaults = config("site-content.sections.{$key}.defaults");
        abort_if($defaults === null, 404);

        $content = array_intersect_key($values, $defaults);

        if (array_key_exists('items', $content)) {
            $content['items'] = array_values(array_map(
                fn (array $item): array => array_map(fn (mixed $value): string => trim((string) $value), $item),
                (array) $content['items'],
            ));
        }

        if ($this->hasImage($key)) {
            $content['image'] = $this->section($key)['image'];
        }

        $this->store($key, $content);
        $this->auditLogger->record('cms.section_updated', $admin, null, ['section' => $key]);
    }

    /**
     * Sections with an optional photo ("image" in their defaults).
     */
    public function hasImage(string $key): bool
    {
        return array_key_exists('image', (array) config("site-content.sections.{$key}.defaults"));
    }

    /**
     * Replace a section's photo. Callers validate the upload with MediaService::imageRules() first;
     * null removes the photo.
     */
    public function replaceImage(string $key, ?UploadedFile $file, User $admin): void
    {
        abort_unless($this->hasImage($key), 404);

        $content = $this->section($key);
        $previous = $content['image'];

        $content['image'] = $file === null ? null : (string) parse_url(
            Storage::disk(MediaService::DISK)->url($this->media->storeWebp($file, self::IMAGE_DIRECTORY)['path']),
            PHP_URL_PATH,
        );

        $this->store($key, $content);

        if (is_string($previous) && str_starts_with($previous, '/storage/'.self::IMAGE_DIRECTORY.'/')) {
            Storage::disk(MediaService::DISK)->delete(substr($previous, strlen('/storage/')));
        }

        $this->auditLogger->record('cms.section_updated', $admin, null, ['section' => $key, 'image' => $file !== null]);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function store(string $key, array $content): void
    {
        SiteContent::query()->updateOrCreate(['section' => $key], ['content' => $content]);
        Cache::forget(self::CACHE_KEY);
    }
}
