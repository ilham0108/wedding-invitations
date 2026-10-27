<?php

namespace App\Services\Invitation;

use Illuminate\Support\Facades\View;

/**
 * Read-only access to the design packs in config('invitation.designs').
 *
 * A design may provide resources/views/designs/{design}/page.blade.php (the page frame)
 * and sections/{type}.blade.php or sections/{type}/{variant}.blade.php (section overrides).
 * Anything it does not provide falls back to the classic section views.
 */
class DesignRegistry
{
    public const DEFAULT = 'classic';

    public function __construct(private SectionRegistry $sections) {}

    /**
     * @return array<string, array{label: string, description: string, fonts: array<int, string>, assets: array<int, string>, features: array<int, string>}>
     */
    public function all(): array
    {
        return config('invitation.designs');
    }

    public function has(?string $design): bool
    {
        return is_string($design) && array_key_exists($design, $this->all());
    }

    public function resolve(?string $design): string
    {
        return $this->has($design) ? $design : self::DEFAULT;
    }

    /**
     * The design's page frame, or null to use the classic page.
     */
    public function pageView(string $design): ?string
    {
        $view = "designs.{$this->resolve($design)}.page";

        return View::exists($view) ? $view : null;
    }

    /**
     * @return array{view: string, native: bool} native is false when the classic view is used as a fallback.
     */
    public function sectionView(string $design, string $type, string $variant): array
    {
        $design = $this->resolve($design);
        $directory = str_replace('_', '-', $type);

        foreach (["designs.{$design}.sections.{$directory}.{$variant}", "designs.{$design}.sections.{$directory}"] as $view) {
            if ($design !== self::DEFAULT && View::exists($view)) {
                return ['view' => $view, 'native' => true];
            }
        }

        return ['view' => $this->sections->view($type, $variant), 'native' => $design === self::DEFAULT];
    }

    /**
     * @return array<int, string>
     */
    public function fonts(string $design): array
    {
        return $this->all()[$this->resolve($design)]['fonts'];
    }

    /**
     * Design-specific capabilities, e.g. "desktop_panel"; fields with a matching "feature" depend on them.
     * Designs without their own page frame get the desktop panel from the "split" desktop layout.
     *
     * @param  array<string, mixed>  $decoration
     * @return array<int, string>
     */
    public function features(?string $design, array $decoration = []): array
    {
        $features = $this->all()[$this->resolve($design)]['features'] ?? [];

        if ($this->hasDesktopLayouts($design) && ($decoration['desktop_layout'] ?? null) === 'split') {
            $features[] = 'desktop_panel';
        }

        return array_values(array_unique($features));
    }

    /**
     * Whether the decoration's "desktop_layout" applies; designs with a page frame lay out wide screens themselves.
     */
    public function hasDesktopLayouts(?string $design): bool
    {
        return $this->pageView($this->resolve($design)) === null;
    }

    /**
     * @return array<int, string>
     */
    public function assets(string $design): array
    {
        return $this->all()[$this->resolve($design)]['assets'];
    }
}
