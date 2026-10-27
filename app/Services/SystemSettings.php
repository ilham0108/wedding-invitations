<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Platform settings the admin edits at /admin/settings (table system_settings).
 * Values are cached as a plain array (never PHP objects) and fall back to DEFINITIONS defaults.
 *
 * Where they apply: site_name replaces config('app.name') at boot (AppServiceProvider),
 * support_email is the dashboard Help link, default_timezone is given to new accounts,
 * registration_enabled closes the sign-up page, and logo/favicon are managed by LogoService/FaviconService.
 */
class SystemSettings
{
    private const CACHE_KEY = 'system_settings.v1';

    /**
     * @var array<string, array{group: string, type: string, label: string, help: ?string, public: bool}>
     */
    public const DEFINITIONS = [
        'site_name' => ['group' => 'general', 'type' => 'string', 'label' => 'Site name', 'help' => 'Shown in page titles, emails and the invitation footer.', 'public' => true],
        'support_email' => ['group' => 'general', 'type' => 'string', 'label' => 'Support email', 'help' => 'Where the Help button in the dashboard sends clients.', 'public' => true],
        'default_timezone' => ['group' => 'general', 'type' => 'string', 'label' => 'Default timezone', 'help' => 'Given to new accounts; each client can change their own.', 'public' => false],
        'logo' => ['group' => 'general', 'type' => 'string', 'label' => 'Logo', 'help' => 'Shown on the website, the dashboard and the login pages. PNG with a transparent background works best, at least 80 px tall.', 'public' => true],
        'favicon' => ['group' => 'general', 'type' => 'string', 'label' => 'Site icon', 'help' => 'Shown in browser tabs, bookmarks and on phone home screens, including invitation pages. Square PNG, at least 512 × 512 px.', 'public' => true],
        'registration_enabled' => ['group' => 'auth', 'type' => 'boolean', 'label' => 'Allow new registrations', 'help' => 'When off, the sign-up page is closed. Existing accounts can still sign in.', 'public' => false],
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:60'],
            'support_email' => ['required', 'email', 'max:120'],
            'default_timezone' => ['required', 'timezone:all'],
            'registration_enabled' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'site_name' => (string) config('app.name'),
            'support_email' => (string) config('mail.from.address'),
            'default_timezone' => 'Asia/Jakarta',
            'logo' => null,
            'favicon' => null,
            'registration_enabled' => true,
        ];
    }

    /**
     * Every setting with its typed value.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => SystemSetting::query()
            ->whereIn('key', array_keys(self::DEFINITIONS))
            ->get()
            ->mapWithKeys(fn (SystemSetting $setting): array => [$setting->key => $setting->typedValue()])
            ->all());

        return [...$this->defaults(), ...array_intersect_key($stored, self::DEFINITIONS)];
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    public function registrationOpen(): bool
    {
        return (bool) $this->get('registration_enabled');
    }

    /**
     * Store validated values (see rules()); only changed keys are written and audited.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values, User $admin, AuditLogger $auditLogger): void
    {
        $current = $this->all();
        $changed = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $definition['type'] === 'boolean' ? (bool) $values[$key] : trim((string) $values[$key]);

            if ($value === $current[$key]) {
                continue;
            }

            SystemSetting::query()->updateOrCreate(['key' => $key], [
                'group' => $definition['group'],
                'type' => $definition['type'],
                'value' => $definition['type'] === 'boolean' ? ($value ? '1' : '0') : $value,
                'is_public' => $definition['public'],
            ]);

            $changed[$key] = ['from' => $current[$key], 'to' => $value];
        }

        Cache::forget(self::CACHE_KEY);

        if ($changed !== []) {
            $auditLogger->record('settings.updated', $admin, null, $changed);
        }
    }
}
