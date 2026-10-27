<?php

namespace App\Services\WhatsApp;

use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Enums\WhatsAppDriver;
use App\Enums\WhatsAppSender;
use App\Exceptions\WhatsAppNotConfiguredException;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppProvider;
use Illuminate\Support\Facades\DB;

/**
 * Resolves provider implementations from configuration; nothing else picks a provider.
 * Platform providers (no owner) are managed by the admin; a client may connect one of their own.
 */
class WhatsAppManager
{
    public function activeConfig(): ?WhatsAppProvider
    {
        return WhatsAppProvider::query()->platform()->active()->latest('updated_at')->first();
    }

    public function isConfigured(): bool
    {
        return $this->activeConfig() !== null;
    }

    public function ownConfig(User $user): ?WhatsAppProvider
    {
        return $user->whatsappProvider()->first();
    }

    /**
     * Provider configuration a campaign sends through. Never falls back from "own" to the platform.
     */
    public function configFor(WhatsAppCampaign $campaign): ?WhatsAppProvider
    {
        return $campaign->sender === WhatsAppSender::Own
            ? WhatsAppProvider::query()->where('user_id', $campaign->user_id)->first()
            : $this->activeConfig();
    }

    public function canSendAs(User $user, WhatsAppSender $sender): bool
    {
        return $sender === WhatsAppSender::Own ? $this->ownConfig($user) !== null : $this->isConfigured();
    }

    /**
     * @throws WhatsAppNotConfiguredException
     */
    public function active(): WhatsAppProviderInterface
    {
        $config = $this->activeConfig() ?? throw new WhatsAppNotConfiguredException;

        return $this->make($config);
    }

    public function make(WhatsAppProvider $config): WhatsAppProviderInterface
    {
        $class = $config->driver->providerClass();

        return new $class($config);
    }

    /**
     * Configuration whose secret must verify an incoming webhook.
     * A client connection is addressed by its ULID; the bare driver URL belongs to the platform.
     */
    public function configForWebhook(WhatsAppDriver $driver, ?string $providerUlid): ?WhatsAppProvider
    {
        $query = WhatsAppProvider::query()->where('driver', $driver);

        return $providerUlid !== null
            ? $query->where('ulid', $providerUlid)->whereNotNull('user_id')->first()
            : $query->platform()->orderByDesc('is_active')->latest('updated_at')->first();
    }

    /**
     * Exactly one platform provider is active at a time. Client connections are untouched.
     */
    public function activate(WhatsAppProvider $provider): void
    {
        DB::transaction(function () use ($provider): void {
            WhatsAppProvider::query()->platform()->whereKeyNot($provider->getKey())->update(['is_active' => false]);
            $provider->update(['is_active' => true]);
        });
    }
}
