<?php

namespace App\Livewire;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppDriver;
use App\Enums\WhatsAppSender;
use App\Livewire\Concerns\Notifies;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppProvider;
use App\Services\AuditLogger;
use App\Services\WhatsApp\WhatsAppProviderService;
use App\Support\VideoEmbed;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "My WhatsApp number": the client connects their own provider account so campaigns
 * can be sent from their personal number. One connection per client; secrets are write-only.
 */
class WhatsAppConnection extends Component
{
    use Notifies;

    /** Personal numbers get banned when they blast too fast. */
    public const MAX_RATE_PER_MINUTE = 60;

    public string $driver = 'fonnte';

    public string $name = '';

    public int $ratePerMinute = 15;

    /** @var array<string, string> */
    public array $credentials = [];

    /** @var array<string, bool> */
    #[Locked]
    public array $storedSecrets = [];

    #[Locked]
    public bool $connected = false;

    public string $testPhone = '';

    public function mount(WhatsAppProviderService $providers): void
    {
        Gate::authorize('viewAny', WhatsAppCampaign::class);

        $this->loadConnection($providers);
    }

    public function updatedDriver(): void
    {
        if (! $this->connected) {
            $this->credentials = [];
            $this->storedSecrets = [];
        }
    }

    public function save(WhatsAppProviderService $providers, AuditLogger $auditLogger): void
    {
        $user = Auth::user();
        $clientDrivers = array_map(fn (WhatsAppDriver $driver): string => $driver->value, WhatsAppDriver::forClients());

        $this->validate(['driver' => ['required', Rule::in($clientDrivers)]], [], ['driver' => mb_strtolower(__('Provider'))]);
        $driver = WhatsAppDriver::from($this->driver);

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'ratePerMinute' => ['required', 'integer', 'min:1', 'max:'.self::MAX_RATE_PER_MINUTE],
            ...$providers->credentialRules($driver, $this->storedSecrets),
        ], [], ['ratePerMinute' => mb_strtolower(__('Messages per minute')), ...$providers->credentialAttributes($driver)]);

        $provider = $user->whatsappProvider()->first() ?? new WhatsAppProvider;

        if ($provider->exists && $provider->driver !== $driver) {
            $this->addError('driver', __('Disconnect your number first to switch to another provider.'));

            return;
        }

        $wasNew = ! $provider->exists;

        if ($wasNew) {
            $provider->user()->associate($user);
            $provider->is_active = true;
        }

        $providers->save($provider, $driver, $this->name, $this->ratePerMinute, $this->credentials);

        $auditLogger->record($wasNew ? 'whatsapp.connection_created' : 'whatsapp.connection_updated', $user, $provider, ['driver' => $driver->value]);

        $this->loadConnection($providers);
        $this->notify('success', __('Your WhatsApp number is connected.'));
    }

    /**
     * Remove the connection. Running "own number" campaigns fail their remaining messages
     * with a clear reason instead of silently switching to the platform number.
     */
    public function disconnect(WhatsAppProviderService $providers, AuditLogger $auditLogger): void
    {
        $user = Auth::user();
        $provider = $user->whatsappProvider()->first();

        if ($provider === null) {
            return;
        }

        $auditLogger->record('whatsapp.connection_deleted', $user, $provider, ['driver' => $provider->driver->value]);
        $provider->delete();

        $this->reset(['driver', 'name', 'ratePerMinute', 'credentials', 'storedSecrets']);
        $this->loadConnection($providers);
    }

    public function sendTest(WhatsAppProviderService $providers): void
    {
        $this->validate(['testPhone' => ['required', 'string', 'max:30']], [], ['testPhone' => __('WhatsApp number')]);

        $provider = Auth::user()->whatsappProvider()->firstOrFail();
        $result = $providers->sendTest($provider, $this->testPhone);

        $result->success
            ? $this->notify('success', __('Test message sent.'))
            : $this->notify('error', __('Test failed: :error', ['error' => (string) $result->error]));
    }

    public function render(WhatsAppProviderService $providers): View
    {
        $provider = Auth::user()->whatsappProvider()->first();
        $driver = WhatsAppDriver::tryFrom($this->driver) ?? WhatsAppDriver::Fonnte;

        return view('livewire.whats-app-connection', [
            'provider' => $provider,
            'drivers' => WhatsAppDriver::forClients(),
            'fields' => $driver->credentialFields(),
            'webhookUrl' => $provider ? $providers->webhookUrl($provider) : null,
            'tutorial' => VideoEmbed::parse(config('whatsapp.tutorials.fonnte'), autoplay: true),
            'activeOwnCampaigns' => $provider
                ? Auth::user()->whatsappCampaigns()
                    ->where('sender', WhatsAppSender::Own)
                    ->whereIn('status', [WhatsAppCampaignStatus::Scheduled, WhatsAppCampaignStatus::Processing])
                    ->count()
                : 0,
        ]);
    }

    private function loadConnection(WhatsAppProviderService $providers): void
    {
        $provider = Auth::user()->whatsappProvider()->first();
        $this->connected = $provider !== null;

        if ($provider !== null) {
            $this->driver = $provider->driver->value;
            $this->name = $provider->name;
            $this->ratePerMinute = $provider->rate_per_minute;
            ['credentials' => $this->credentials, 'storedSecrets' => $this->storedSecrets] = $providers->formState($provider);
        }
    }
}
