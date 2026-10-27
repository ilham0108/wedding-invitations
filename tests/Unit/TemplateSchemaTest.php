<?php

namespace Tests\Unit;

use App\Services\Template\TemplateSchema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplateSchemaTest extends TestCase
{
    protected bool $seed = false;

    private function schema(): TemplateSchema
    {
        return app(TemplateSchema::class);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, array<int, string>>
     */
    private function errorsFor(array $definition): array
    {
        try {
            $this->schema()->validate($definition);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('The definition was expected to be invalid.');
    }

    public function test_sections_are_returned_in_canonical_form(): void
    {
        $definition = $this->schema()->validate([
            'schema' => ['sections' => ['cover', ['type' => 'event', 'variant' => 'list', 'background' => 'muted']]],
        ]);

        $this->assertSame(TemplateSchema::VERSION, $definition['schema_version']);
        $this->assertSame([
            ['type' => 'cover', 'variant' => 'centered', 'background' => 'default'],
            ['type' => 'event', 'variant' => 'list', 'background' => 'muted'],
        ], $definition['schema']['sections']);
        $this->assertSame([], $definition['theme']);
        $this->assertSame([], $definition['decoration']);
    }

    public function test_section_structure_is_validated(): void
    {
        $errors = $this->errorsFor(['schema' => ['sections' => [
            'cover',
            'unknown-type',
            ['type' => 'gallery', 'variant' => 'framed'],
            ['type' => 'quote', 'background' => 'neon'],
        ]]]);

        $this->assertArrayNotHasKey('schema.sections.0.type', $errors);
        $this->assertArrayHasKey('schema.sections.1.type', $errors);
        $this->assertArrayHasKey('schema.sections.2.variant', $errors);
        $this->assertArrayHasKey('schema.sections.3.background', $errors);
    }

    public function test_a_template_needs_at_least_one_section_and_a_supported_version(): void
    {
        $errors = $this->errorsFor(['schema_version' => TemplateSchema::VERSION + 1, 'schema' => ['sections' => []]]);

        $this->assertArrayHasKey('schema.sections', $errors);
        $this->assertArrayHasKey('schema_version', $errors);
    }

    public function test_theme_and_decoration_values_are_validated(): void
    {
        $errors = $this->errorsFor([
            'schema' => ['sections' => ['cover']],
            'theme' => ['preset' => 'neon', 'primary_color' => 'red', 'font_heading' => 'Comic Sans', 'button_style' => 'pill'],
            'decoration' => ['divider' => 'lightning', 'texture' => 'paper'],
        ]);

        $this->assertArrayHasKey('theme.preset', $errors);
        $this->assertArrayHasKey('theme.primary_color', $errors);
        $this->assertArrayHasKey('theme.font_heading', $errors);
        $this->assertArrayNotHasKey('theme.button_style', $errors);
        $this->assertArrayHasKey('decoration.divider', $errors);
        $this->assertArrayNotHasKey('decoration.texture', $errors);
    }

    public function test_unknown_theme_and_decoration_keys_are_dropped(): void
    {
        $definition = $this->schema()->validate([
            'schema' => ['sections' => ['cover']],
            'theme' => ['preset' => 'luxury', 'primary_color' => '#112233', 'custom_css' => 'body { display: none }'],
            'decoration' => ['texture' => 'grain', 'script' => 'alert(1)'],
        ]);

        $this->assertSame(['preset' => 'luxury', 'primary_color' => '#112233'], $definition['theme']);
        $this->assertSame(['texture' => 'grain'], $definition['decoration']);
    }

    public function test_defaults_and_sample_content_follow_the_section_fields(): void
    {
        $errors = $this->errorsFor([
            'schema' => ['sections' => ['cover', 'event']],
            'default_configuration' => ['not_a_section' => ['heading' => 'x']],
            'sample_content' => ['event' => ['events' => [['title' => 'Akad', 'date' => '12/06/2027']]]],
        ]);

        $this->assertArrayHasKey('default_configuration', $errors);
        $this->assertArrayHasKey('sample_content.event.events.0.date', $errors);
    }

    public function test_unknown_configuration_keys_are_pruned_without_adding_defaults(): void
    {
        $definition = $this->schema()->validate([
            'schema' => ['sections' => ['couple']],
            'default_configuration' => ['couple' => ['heading' => 'Kedua Mempelai', 'evil' => '<script>']],
            'sample_content' => ['couple' => ['people' => [['name' => 'Romeo', 'injected' => 'x']]]],
        ]);

        $this->assertSame(['couple' => ['heading' => 'Kedua Mempelai']], $definition['default_configuration']);
        $this->assertSame(['couple' => ['people' => [['name' => 'Romeo']]]], $definition['sample_content']);
    }
}
