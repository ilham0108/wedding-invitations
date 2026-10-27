<?php

namespace App\Livewire\Concerns;

use App\Enums\IllustrationCategory;
use App\Models\Illustration;
use App\Services\IllustrationService;
use App\Services\Invitation\SectionRegistry;
use App\Services\MediaService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Shared behaviour of the generated section form (livewire.partials.section-field):
 * repeaters, colour palettes, image uploads, galleries and the illustration picker.
 * Used by the client section editor and the admin template builder.
 *
 * The component provides a locked $type, save(), and where uploaded images are stored.
 */
trait EditsSectionFields
{
    /** @var array<string, mixed> */
    public array $config = [];

    /** @var array<string, mixed> Single-image uploads keyed by config path, e.g. "people.0.photo". */
    public array $uploads = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $galleryUploads = [];

    /** Config path of the image field whose illustration picker is open, e.g. "people.0.photo". */
    public ?string $pickerPath = null;

    abstract public function save(): void;

    /**
     * Store a validated upload and return the URL to put into the configuration.
     */
    abstract protected function storeUploadedImage(TemporaryUploadedFile $file): string;

    abstract protected function canUseIllustration(Illustration $illustration): bool;

    /**
     * Fields this editor may change. The template builder edits all of them.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function editableFields(): array
    {
        return app(SectionRegistry::class)->fields($this->type);
    }

    public function updatedConfig(): void
    {
        $this->save();
    }

    public function addItem(string $path): void
    {
        $field = $this->field($path, 'repeater');
        $items = Arr::get($this->config, $path, []);

        if (count($items) >= ($field['max'] ?? 20)) {
            return;
        }

        $items[] = collect($field['fields'])->mapWithKeys(fn (array $subField): array => [
            $subField['key'] => $subField['type'] === 'toggle' ? false : '',
        ])->all();

        Arr::set($this->config, $path, $items);
        $this->save();
    }

    public function removeItem(string $path, int $index): void
    {
        $items = Arr::get($this->config, $path, []);
        unset($items[$index]);

        Arr::set($this->config, $path, array_values($items));
        $this->save();
    }

    /**
     * wire:sort handler shared by repeaters, galleries, and palettes ("$path" is bound per list).
     */
    public function sortItem(string $path, int|string $index, int $position): void
    {
        $items = array_values(Arr::get($this->config, $path, []));
        $index = (int) $index;

        if (! array_key_exists($index, $items)) {
            return;
        }

        $moved = array_splice($items, $index, 1);
        array_splice($items, max(0, min($position, count($items))), 0, $moved);

        Arr::set($this->config, $path, $items);
        $this->save();
    }

    public function addColor(string $path): void
    {
        $field = $this->field($path, 'colors');
        $colors = Arr::get($this->config, $path, []);

        if (count($colors) < ($field['max'] ?? 8)) {
            $colors[] = '#cccccc';
            Arr::set($this->config, $path, $colors);
            $this->save();
        }
    }

    /**
     * Single image upload for an "image" field. $key is the config path (e.g. "people.0.photo").
     */
    public function updatedUploads(mixed $file, string $key): void
    {
        $this->validate(["uploads.{$key}" => ['required', ...MediaService::imageRules()]]);

        Arr::set($this->config, $key, $this->storeUploadedImage($file));
        $this->uploads = [];
        $this->save();
    }

    public function updatedGalleryUploads(): void
    {
        $field = collect($this->editableFields())->firstWhere('type', 'images');
        abort_if($field === null, 404);

        $current = Arr::get($this->config, $field['key'], []);
        $remaining = ($field['max'] ?? 30) - count($current);

        $this->validate([
            'galleryUploads' => ['array', 'max:'.max(0, $remaining)],
            'galleryUploads.*' => MediaService::imageRules(),
        ], ['galleryUploads.max' => __('You can add :max more photos.', ['max' => max(0, $remaining)])]);

        foreach ($this->galleryUploads as $upload) {
            $current[] = $this->storeUploadedImage($upload);
        }

        Arr::set($this->config, $field['key'], $current);
        $this->galleryUploads = [];
        $this->save();
    }

    public function openPicker(string $path): void
    {
        $this->illustrationField($path);
        $this->pickerPath = $path;
    }

    public function closePicker(): void
    {
        $this->pickerPath = null;
    }

    /**
     * Use a library illustration instead of a photo. Only active illustrations of the
     * category the field accepts can be chosen, so no arbitrary URL can be injected.
     */
    public function pickIllustration(string $illustrationUlid): void
    {
        if ($this->pickerPath === null) {
            return;
        }

        $path = $this->pickerPath;
        $field = $this->illustrationField($path);

        $illustration = Illustration::query()
            ->active()
            ->where('category', $field['illustrations'])
            ->where('ulid', $illustrationUlid)
            ->firstOrFail();

        if (! $this->canUseIllustration($illustration)) {
            $this->addError('picker', __('This illustration is only available on Premium.'));

            return;
        }

        if ($field['type'] === 'images') {
            $images = Arr::get($this->config, $path, []);

            if (count($images) >= ($field['max'] ?? 30)) {
                $this->addError('galleryUploads', __('The gallery is full. Remove a photo first.'));

                return;
            }

            Arr::set($this->config, $path, [...$images, $illustration->url()]);
        } else {
            Arr::set($this->config, $path, $illustration->url());
        }

        $this->pickerPath = null;
        $this->save();
    }

    /**
     * View data for the illustration picker partial.
     *
     * @return array{pickerCategory: ?IllustrationCategory, pickerIllustrations: Collection<int, Illustration>}
     */
    protected function pickerViewData(IllustrationService $illustrations): array
    {
        $pickerCategory = $this->pickerPath !== null
            ? IllustrationCategory::tryFrom((string) ($this->illustrationField($this->pickerPath)['illustrations'] ?? ''))
            : null;

        return [
            'pickerCategory' => $pickerCategory,
            'pickerIllustrations' => $pickerCategory ? $illustrations->forCategory($pickerCategory) : collect(),
        ];
    }

    /**
     * Validate the form against the section's field rules.
     *
     * @param  array<string, array<int, mixed>>  $extraRules
     * @param  array<string, string>  $extraAttributes
     */
    protected function validateConfig(array $extraRules = [], array $extraAttributes = []): void
    {
        $registry = app(SectionRegistry::class);

        $this->validate(
            [...$registry->rules($this->type), ...$extraRules],
            [],
            [...$registry->attributes($this->type), ...$extraAttributes],
        );
    }

    /**
     * Resolve an image field (top-level or inside a repeater item) that accepts illustrations.
     *
     * @return array<string, mixed>
     */
    private function illustrationField(string $path): array
    {
        $segments = array_values(array_filter(explode('.', $path), fn (string $segment): bool => ! ctype_digit($segment)));
        $fields = $this->editableFields();
        $field = null;

        foreach ($segments as $segment) {
            $field = collect($fields)->firstWhere('key', $segment);
            abort_if($field === null, 404);
            $fields = $field['fields'] ?? [];
        }

        abort_unless($field !== null && in_array($field['type'], ['image', 'images'], true) && ! empty($field['illustrations']), 404);

        return $field;
    }

    /**
     * Resolve a top-level field definition by path and require its type.
     *
     * @return array<string, mixed>
     */
    private function field(string $path, string $type): array
    {
        $field = collect($this->editableFields())->firstWhere('key', $path);
        abort_unless($field !== null && $field['type'] === $type, 404);

        return $field;
    }
}
