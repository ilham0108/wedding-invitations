<?php

namespace Tests\Feature;

use App\Enums\RsvpStatus;
use App\Enums\WhatsAppDriver;
use App\Models\User;
use App\Services\WhatsApp\MessageRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Number;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Guards the Indonesian translation: every translatable string used by the app must exist in lang/id.json.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_translation_key_has_an_indonesian_translation(): void
    {
        $translations = json_decode((string) file_get_contents(lang_path('id.json')), true, flags: JSON_THROW_ON_ERROR);

        $keys = array_unique([...$this->staticKeys(), ...$this->dynamicKeys()]);
        $this->assertGreaterThan(400, count($keys), 'Key extraction found suspiciously few strings.');

        $missing = array_values(array_filter($keys, fn (string $key): bool => ! array_key_exists($key, $translations)));

        $this->assertSame([], $missing, 'Missing Indonesian translations: '.implode(' | ', $missing));
    }

    public function test_pages_render_in_indonesian(): void
    {
        App::setLocale('id');

        $this->get('/')->assertOk()->assertSee('Undangan Digital untuk Momen Spesial Anda')->assertSee('Beranda');
        $this->get('/login')->assertOk()->assertSee('Selamat datang kembali')->assertSee('Kata sandi');

        $this->actingAs(User::factory()->client()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Undangan Saya')
            ->assertSee('Anda belum membuat undangan.');
    }

    public function test_labels_dates_and_validation_are_indonesian(): void
    {
        App::setLocale('id');

        $this->assertSame('Hadir', RsvpStatus::Attending->label());
        $this->assertSame('1.250', Number::format(1250));
        $this->assertSame('Sabtu, 12 Juni 2027', now()->setDate(2027, 6, 12)->translatedFormat('l, d F Y'));
        $this->assertSame('Nomor HP wajib diisi.', validator(['phone' => ''], ['phone' => 'required'])->errors()->first('phone'));

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => 'Email atau kata sandi salah.']);
    }

    /**
     * @return array<int, string>
     */
    private function staticKeys(): array
    {
        $keys = [];
        $pattern = '/(?:__|trans_choice|@lang)\(\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/';

        foreach ([app_path(), resource_path('views')] as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    preg_match_all($pattern, (string) file_get_contents($file->getPathname()), $matches);

                    foreach ($matches[1] as $literal) {
                        $keys[] = stripcslashes(substr($literal, 1, -1));
                    }
                }
            }
        }

        // Group keys such as "auth.password" live in lang/id/*.php, not in id.json.
        return array_values(array_filter($keys, fn (string $key): bool => preg_match('/^[a-z_]+\.[a-z_.]+$/', $key) !== 1));
    }

    /**
     * Strings translated through variables: navigation, section registry, theme options, enum labels.
     *
     * @return array<int, string>
     */
    private function dynamicKeys(): array
    {
        $keys = array_column(array_merge(...array_values(config('navigation'))), 'label');

        // Default copy is translated per text/textarea field, including inside default list items.
        $collectDefaults = function (array $fields, array $values) use (&$collectDefaults, &$keys): void {
            foreach ($fields as $field) {
                $value = $values[$field['key']] ?? null;

                if (in_array($field['type'], ['text', 'textarea'], true) && is_string($value) && $value !== '') {
                    $keys[] = $value;
                }

                if ($field['type'] === 'repeater' && is_array($value)) {
                    foreach (array_filter($value, 'is_array') as $item) {
                        $collectDefaults($field['fields'], $item);
                    }
                }
            }
        };

        foreach (config('invitation.sections') as $type => $definition) {
            $keys[] = $definition['label'];
            array_push($keys, ...array_map(fn (string $variant): string => str_replace('-', ' ', $variant), $definition['variants']));
            $collectDefaults(config("invitation.fields.{$type}", []), $definition['defaults']);
        }

        $walk = function (array $fields) use (&$walk, &$keys): void {
            foreach ($fields as $field) {
                $keys[] = $field['label'];
                array_push($keys, ...array_values($field['options'] ?? []));
                $walk($field['fields'] ?? []);
            }
        };
        array_map($walk, config('invitation.fields'));
        $walk(config('invitation.common_fields'));

        foreach (config('invitation.designs') as $design) {
            array_push($keys, $design['label'], $design['description']);
        }

        array_push($keys, ...array_map('ucfirst', array_keys(config('invitation.theme_presets'))));
        array_push($keys, ...array_map('ucfirst', array_merge(...array_values(config('invitation.theme_options')))));
        array_push($keys, ...array_values(array_map(fn (array $font): string => ucfirst($font['category']), config('invitation.fonts'))));

        array_push($keys, ...array_values(array_column(config('plans.features'), 'label')));

        foreach (WhatsAppDriver::cases() as $driver) {
            array_push($keys, ...array_column($driver->credentialFields(), 'label'));
        }

        App::setLocale('en');
        array_push($keys, MessageRenderer::defaultTemplate(), ...array_values(MessageRenderer::variables()));

        foreach (glob(app_path('Enums').'/*.php') as $file) {
            $enum = 'App\\Enums\\'.basename($file, '.php');
            foreach ($enum::cases() as $case) {
                if (method_exists($case, 'label')) {
                    $keys[] = $case->label();
                }
            }
        }

        return $keys;
    }
}
