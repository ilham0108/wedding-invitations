<?php

namespace App\Enums;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Services\WhatsApp\Providers\FonnteProvider;
use App\Services\WhatsApp\Providers\LogProvider;
use App\Services\WhatsApp\Providers\MetaWhatsAppProvider;
use App\Services\WhatsApp\Providers\TwilioProvider;
use App\Services\WhatsApp\Providers\WablasProvider;

/**
 * Supported WhatsApp providers (rules.md section 39). Adding a provider means adding
 * a case here plus one provider class; nothing else hard-codes provider logic.
 */
enum WhatsAppDriver: string
{
    case Log = 'log';
    case Meta = 'meta';
    case Fonnte = 'fonnte';
    case Wablas = 'wablas';
    case Twilio = 'twilio';

    public function label(): string
    {
        return __(match ($this) {
            self::Log => 'Log only (development)',
            self::Meta => 'Meta WhatsApp Cloud API',
            self::Fonnte => 'Fonnte',
            self::Wablas => 'Wablas',
            self::Twilio => 'Twilio',
        });
    }

    /**
     * @return class-string<WhatsAppProviderInterface>
     */
    public function providerClass(): string
    {
        return match ($this) {
            self::Log => LogProvider::class,
            self::Meta => MetaWhatsAppProvider::class,
            self::Fonnte => FonnteProvider::class,
            self::Wablas => WablasProvider::class,
            self::Twilio => TwilioProvider::class,
        };
    }

    /**
     * Credential fields for the admin form. Secret values are stored encrypted and never sent back to the browser.
     *
     * @return array<int, array{key: string, label: string, secret: bool, required: bool, placeholder?: string}>
     */
    public function credentialFields(): array
    {
        return match ($this) {
            self::Log => [],
            self::Meta => [
                ['key' => 'phone_number_id', 'label' => 'Phone number ID', 'secret' => false, 'required' => true],
                ['key' => 'access_token', 'label' => 'Access token', 'secret' => true, 'required' => true],
                ['key' => 'api_version', 'label' => 'Graph API version', 'secret' => false, 'required' => false, 'placeholder' => 'v21.0'],
                ['key' => 'app_secret', 'label' => 'App secret (webhook signature)', 'secret' => true, 'required' => false],
                ['key' => 'verify_token', 'label' => 'Webhook verify token', 'secret' => true, 'required' => false],
            ],
            self::Fonnte => [
                ['key' => 'token', 'label' => 'API token', 'secret' => true, 'required' => true],
            ],
            self::Wablas => [
                ['key' => 'base_url', 'label' => 'Server URL', 'secret' => false, 'required' => true, 'placeholder' => 'https://jkt.wablas.com'],
                ['key' => 'token', 'label' => 'API token', 'secret' => true, 'required' => true],
                ['key' => 'secret_key', 'label' => 'Secret key (optional)', 'secret' => true, 'required' => false],
            ],
            self::Twilio => [
                ['key' => 'account_sid', 'label' => 'Account SID', 'secret' => false, 'required' => true],
                ['key' => 'auth_token', 'label' => 'Auth token', 'secret' => true, 'required' => true],
                ['key' => 'from', 'label' => 'Sender WhatsApp number', 'secret' => false, 'required' => true, 'placeholder' => '+14155238886'],
            ],
        };
    }

    /**
     * Drivers a client may use for their own number (the log driver is for the platform's development setup).
     *
     * @return array<int, self>
     */
    public static function forClients(): array
    {
        return [self::Fonnte, self::Wablas, self::Meta, self::Twilio];
    }

    public function supportsStatusWebhook(): bool
    {
        return in_array($this, [self::Meta, self::Twilio], true);
    }
}
