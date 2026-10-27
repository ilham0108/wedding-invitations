<?php

namespace App\Services\WhatsApp;

use App\Enums\WhatsAppDriver;
use App\Models\WhatsAppProvider;
use App\Services\Guest\GuestService;
use App\Services\WhatsApp\Data\SendResult;

/**
 * Shared provider-form behavior for the admin (platform providers) and clients (own number).
 * Secret credentials are write-only: they are never returned to the form.
 */
class WhatsAppProviderService
{
    public function __construct(private WhatsAppManager $whatsapp) {}

    /**
     * Form state for editing: non-secret values, blank secrets, and which secrets are already stored.
     *
     * @return array{credentials: array<string, string>, storedSecrets: array<string, bool>}
     */
    public function formState(WhatsAppProvider $provider): array
    {
        $credentials = [];
        $storedSecrets = [];

        foreach ($provider->driver->credentialFields() as $field) {
            if ($field['secret']) {
                $storedSecrets[$field['key']] = $provider->credential($field['key']) !== null;
                $credentials[$field['key']] = '';
            } else {
                $credentials[$field['key']] = (string) $provider->credential($field['key']);
            }
        }

        return ['credentials' => $credentials, 'storedSecrets' => $storedSecrets];
    }

    /**
     * @param  array<string, bool>  $storedSecrets
     * @return array<string, array<int, string>>
     */
    public function credentialRules(WhatsAppDriver $driver, array $storedSecrets): array
    {
        $rules = [];

        foreach ($driver->credentialFields() as $field) {
            $required = $field['required'] && ! ($field['secret'] && ($storedSecrets[$field['key']] ?? false));
            $rules['credentials.'.$field['key']] = [$required ? 'required' : 'nullable', 'string', 'max:2000'];

            if ($field['key'] === 'base_url') {
                $rules['credentials.base_url'][] = 'url:https';
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function credentialAttributes(WhatsAppDriver $driver): array
    {
        $attributes = [];

        foreach ($driver->credentialFields() as $field) {
            $attributes['credentials.'.$field['key']] = __($field['label']);
        }

        return $attributes;
    }

    /**
     * Save a provider. A blank secret on an existing provider keeps the stored value.
     *
     * @param  array<string, mixed>  $input  credential values from the form
     */
    public function save(WhatsAppProvider $provider, WhatsAppDriver $driver, string $name, int $ratePerMinute, array $input): WhatsAppProvider
    {
        $credentials = $provider->exists ? (array) $provider->credentials : [];

        foreach ($driver->credentialFields() as $field) {
            $value = trim((string) ($input[$field['key']] ?? ''));

            if ($field['secret'] && $value === '' && $provider->exists) {
                continue;
            }

            $credentials[$field['key']] = $value;
        }

        $provider->fill([
            'driver' => $driver,
            'name' => trim($name),
            'rate_per_minute' => $ratePerMinute,
            'credentials' => $credentials,
        ])->save();

        return $provider;
    }

    /**
     * Send one real test message through the provider.
     */
    public function sendTest(WhatsAppProvider $provider, string $rawPhone): SendResult
    {
        $phone = GuestService::normalizePhone($rawPhone);

        if (! GuestService::isValidPhone($phone)) {
            return SendResult::failed(__('Phone number format is invalid.'));
        }

        $result = $this->whatsapp->make($provider)->sendMessage(
            $phone,
            __('Test message from :app. Your WhatsApp provider is working.', ['app' => config('app.name')]),
        );

        if ($result->success) {
            $provider->forceFill(['last_tested_at' => now()])->save();
        }

        return $result;
    }

    public function webhookUrl(WhatsAppProvider $provider): ?string
    {
        if (! $provider->driver->supportsStatusWebhook()) {
            return null;
        }

        return $provider->isPlatform()
            ? route('webhooks.whatsapp', ['driver' => $provider->driver->value])
            : route('webhooks.whatsapp.connection', ['driver' => $provider->driver->value, 'provider' => $provider->ulid]);
    }
}
