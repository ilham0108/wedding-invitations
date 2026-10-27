<?php

namespace Tests\Unit;

use App\Services\Invitation\SectionRegistry;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Tests\TestCase;

class SectionRegistryTest extends TestCase
{
    protected bool $seed = false;

    public function test_all_sections_from_rules_are_registered_with_a_view(): void
    {
        $registry = new SectionRegistry;

        $this->assertSame([
            'cover', 'couple', 'quote', 'countdown', 'event', 'story', 'timeline', 'gallery',
            'video', 'rsvp', 'wishes', 'gift', 'maps', 'dress_code', 'contact', 'closing', 'health_protocol',
        ], $registry->types());

        foreach ($registry->types() as $type) {
            $this->assertNotEmpty($registry->variants($type));

            foreach ($registry->variants($type) as $variant) {
                $this->assertTrue(View::exists($registry->view($type, $variant)), "Missing view for section [{$type}] variant [{$variant}].");
            }
        }
    }

    public function test_view_resolves_one_file_per_variant(): void
    {
        $registry = new SectionRegistry;

        $this->assertSame('invitation.sections.cover.framed', $registry->view('cover', 'framed'));
        $this->assertSame('invitation.sections.cover.centered', $registry->view('cover', 'missing'));
        $this->assertSame('invitation.sections.dress-code.default', $registry->view('dress_code'));
    }

    public function test_every_default_key_has_an_editor_field(): void
    {
        $registry = new SectionRegistry;

        foreach ($registry->types() as $type) {
            $fieldKeys = array_column($registry->fields($type), 'key');

            $this->assertNotEmpty($fieldKeys, "Section [{$type}] has no editor fields.");
            $this->assertSame([], array_diff(array_keys($registry->defaults($type)), $fieldKeys), "Section [{$type}] has defaults without fields.");
        }
    }

    public function test_sanitize_drops_unknown_keys_and_casts_types(): void
    {
        $clean = (new SectionRegistry)->sanitize('couple', [
            'heading' => '  Hosts  ',
            'evil' => '<script>',
            'people' => [['name' => 'John', 'injected' => 'x'], 'not-an-array'],
        ]);

        $this->assertSame('Hosts', $clean['heading']);
        $this->assertArrayNotHasKey('evil', $clean);
        $this->assertSame('John', $clean['people'][0]['name']);
        $this->assertArrayNotHasKey('injected', $clean['people'][0]);
        $this->assertSame('', $clean['people'][1]['name']);
    }

    public function test_rules_cover_nested_repeater_fields(): void
    {
        $rules = (new SectionRegistry)->rules('event');

        $this->assertContains('date_format:Y-m-d', $rules['config.events.*.date']);
        $this->assertContains('url:http,https', $rules['config.events.*.maps_url']);
        $this->assertContains('max:10', $rules['config.events']);
    }

    public function test_unknown_variant_falls_back_to_first_variant(): void
    {
        $registry = new SectionRegistry;

        $this->assertSame('framed', $registry->resolveVariant('cover', 'framed'));
        $this->assertSame('centered', $registry->resolveVariant('cover', 'does-not-exist'));
        $this->assertSame('centered', $registry->resolveVariant('cover', null));
    }

    public function test_unknown_section_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SectionRegistry)->definition('not-a-section');
    }
}
