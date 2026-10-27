<?php

namespace Tests\Unit;

use App\Services\Invitation\DesignRegistry;
use App\Services\Invitation\SectionRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * A design pack must honour every option the builder and editor offer for the sections it
 * overrides: all layouts (variants) and all fields. Otherwise a choice silently does nothing.
 */
class DesignCoverageTest extends TestCase
{
    protected bool $seed = false;

    /**
     * Fields a view reads through a value InvitationRenderer derives from them.
     *
     * @var array<string, array<int, string>>
     */
    private const DERIVED = [
        'instagram' => ['socials'], 'tiktok' => ['socials'], 'facebook' => ['socials'], 'x' => ['socials'], 'youtube' => ['socials'],
        'date' => ['date_label'],
        'add_to_calendar' => ['calendar_url'],
        'icon' => ['icon_class'],
        'deadline' => ['deadline_label'],
        'url' => ['embed_url'],
        'latitude' => ['embed_url'], 'longitude' => ['embed_url'],
        // Handled by the shared RSVP form partial and the renderer's interaction data.
        'max_guests' => ['rsvp-form'], 'ask_meal_preference' => ['rsvp-form'], 'meal_options' => ['rsvp-form'],
        'show_messages' => ['messages'],
    ];

    public function test_design_packs_support_every_layout_of_the_sections_they_override(): void
    {
        $designs = app(DesignRegistry::class);
        $sections = app(SectionRegistry::class);

        foreach (array_keys($designs->all()) as $design) {
            foreach ($sections->types() as $type) {
                $variants = $sections->variants($type);
                $native = array_filter($variants, fn (string $variant): bool => $designs->sectionView($design, $type, $variant)['native']);

                if ($design === DesignRegistry::DEFAULT || $native === []) {
                    continue;
                }

                $this->assertSame($variants, array_values($native), "Design [{$design}] overrides [{$type}] but not all of its layouts.");

                // Several layouts may share one view only when that view branches on the layout.
                $views = array_map(fn (string $variant): string => $designs->sectionView($design, $type, $variant)['view'], $variants);

                if (count($variants) > 1 && count(array_unique($views)) < count($views)) {
                    $source = File::get(View::getFinder()->find($views[0]));

                    if ($type === 'cover' && $designs->pageView($design)) {
                        $source .= File::get(View::getFinder()->find($designs->pageView($design)));
                    }

                    $this->assertMatchesRegularExpression('/\$variant\b|\[\'variant\'\]/', $source, "Design [{$design}] renders all [{$type}] layouts the same way.");
                }
            }
        }
    }

    public function test_design_packs_use_every_field_of_the_sections_they_override(): void
    {
        $designs = app(DesignRegistry::class);
        $sections = app(SectionRegistry::class);

        foreach (array_keys($designs->all()) as $design) {
            if ($design === DesignRegistry::DEFAULT) {
                continue;
            }

            foreach ($sections->types() as $type) {
                foreach ($sections->variants($type) as $variant) {
                    $view = $designs->sectionView($design, $type, $variant);

                    if (! $view['native']) {
                        continue;
                    }

                    $source = $this->sourceWithIncludes($view['view']);

                    if ($type === 'cover' && $designs->pageView($design)) {
                        $source .= $this->sourceWithIncludes($designs->pageView($design));
                    }

                    // Common fields (e.g. background_photo) are rendered by the page frame, not the section views.
                    $own = array_diff($this->fieldKeys($sections->fields($type), $variant), array_column(config('invitation.common_fields'), 'key'));

                    foreach ($own as $key) {
                        $names = [$key, ...(self::DERIVED[$key] ?? [])];
                        $used = collect($names)->contains(fn (string $name): bool => preg_match('/[\'"]'.preg_quote($name, '/').'[\'"]/', $source) === 1);

                        $this->assertTrue($used, "Design [{$design}] ignores the [{$type}.{$key}] field in the [{$variant}] layout.");
                    }
                }
            }
        }
    }

    public function test_design_pages_render_the_scroll_backdrop_and_section_markers(): void
    {
        $designs = app(DesignRegistry::class);

        foreach (array_keys($designs->all()) as $design) {
            $page = $designs->pageView($design) ?? 'invitation.show';
            $source = File::get(View::getFinder()->find($page));

            $this->assertStringContainsString('invitation.partials.scroll-backdrop', $source, "Design [{$design}] ignores section background photos.");
            $this->assertStringContainsString('invitation.partials.section', $source, "Design [{$design}] does not use the shared section markup.");

            // The shared section markup brings the ornaments; a design page must add them to its own section/cover markup.
            if ($designs->pageView($design)) {
                $this->assertStringContainsString('invitation.partials.ornaments', $source, "Design [{$design}] has markup without ornaments.");
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    private function fieldKeys(array $fields, string $variant): array
    {
        $keys = [];

        foreach ($fields as $field) {
            if (isset($field['variants']) && ! in_array($variant, $field['variants'], true)) {
                continue;
            }

            $keys[] = $field['key'];

            if ($field['type'] === 'repeater') {
                array_push($keys, ...$this->fieldKeys($field['fields'], $variant));
            }
        }

        return $keys;
    }

    /**
     * Blade source of a view plus the views it includes (one level is enough for design packs).
     */
    private function sourceWithIncludes(string $view, int $depth = 2): string
    {
        $source = File::get(View::getFinder()->find($view));

        if ($depth > 0 && preg_match_all("/@include\('([^']+)'/", $source, $matches)) {
            foreach ($matches[1] as $included) {
                $source .= $this->sourceWithIncludes($included, $depth - 1);
            }
        }

        return $source;
    }
}
