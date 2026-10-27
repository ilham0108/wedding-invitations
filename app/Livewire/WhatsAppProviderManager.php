<?php

namespace App\Livewire;

use App\Enums\WhatsAppDriver;
use App\Livewire\Concerns\Notifies;
use App\Models\WhatsAppProvider;
use App\Services\AuditLogger;
use App\Services\WhatsApp\WhatsAppManager;
use App\Services\WhatsApp\WhatsAppProviderService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin configuration of the platform's WhatsApp providers (rules.md section 39).
 * Clients' own connections are not listed here. Secret credentials are write-only.
 */
class WhatsAppProviderManager extends Component
{
    use Notifies;

    public bool $showForm = false;

    #[Locked]
    public ?string $editingUlid = null;

    public string $driver = 'fonnte';

    public string $name = '';

    public int $ratePerMinute = 20;

    /** @var array<string, string> */
    public array $credentials = [];

    /** @var array<string, bool> Secret keys that already have a stored value. */
    #[Locked]
    public array $storedSecrets = [];

    public string $testPhone = '';

    public function mount(): void
    {
        Gate::authorize('whatsapp-providers.manage');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(string $providerUlid, WhatsAppProviderService $providers): void
    {
        $provider = $this->provider($providerUlid);

        $this->resetForm();
        $this->editingUlid = $provider->ulid;
        $this->driver = $provider->driver->value;
        $this->name = $provider->name;
        $this->ratePerMinute = $provider->rate_per_minute;
        ['credentials' => $this->credentials, 'storedSecrets' => $this->storedSecrets] = $providers->formState($provider);
        $this->showForm = true;
    }

    public function updatedDriver(): void
    {
        if ($this->editingUlid === null) {
            $this->credentials = [];
            $this->storedSecrets = [];
        }
    }

    public function save(WhatsAppManager $whatsapp, WhatsAppProviderService $providers, AuditLogger $auditLogger): void
    {
        Gate::authorize('whatsapp-providers.manage');

        $this->validate(['driver' => ['required', Rule::enum(WhatsAppDriver::class)]], [], ['driver' => mb_strtolower(__('Provider'))]);
        $driver = WhatsAppDriver::from($this->driver);

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'ratePerMinute' => ['required', 'integer', 'min:1', 'max:600'],
            ...$providers->credentialRules($driver, $this->storedSecrets),
        ], [], ['ratePerMinute' => mb_strtolower(__('Messages per minute')), ...$providers->credentialAttributes($driver)]);

        $provider = $this->editingUlid ? $this->provider($this->editingUlid) : new WhatsAppProvider;

        if ($provider->exists && $provider->driver !== $driver) {
            $this->addError('driver', __('The provider type cannot be changed. Add a new provider instead.'));

            return;
        }

        $wasNew = ! $provider->exists;
        $providers->save($provider, $driver, $this->name, $this->ratePerMinute, $this->credentials);

        if ($wasNew && ! $whatsapp->isConfigured()) {
            $whatsapp->activate($provider);
        }

        $auditLogger->record($wasNew ? 'whatsapp.provider_created' : 'whatsapp.provider_updated', Auth::user(), $provider, ['driver' => $driver->value]);

        $this->showForm = false;
        $this->resetForm();
    }

    public function activate(string $providerUlid, WhatsAppManager $whatsapp, AuditLogger $auditLogger): void
    {
        Gate::authorize('whatsapp-providers.manage');

        $provider = $this->provider($providerUlid);
        $whatsapp->activate($provider);
        $auditLogger->record('whatsapp.provider_activated', Auth::user(), $provider, ['driver' => $provider->driver->value]);
    }

    public function delete(string $providerUlid, AuditLogger $auditLogger): void
    {
        Gate::authorize('whatsapp-providers.manage');

        $provider = $this->provider($providerUlid);
        $auditLogger->record('whatsapp.provider_deleted', Auth::user(), $provider, ['driver' => $provider->driver->value]);
        $provider->delete();
    }

    public function sendTest(string $providerUlid, WhatsAppProviderService $providers): void
    {
        Gate::authorize('whatsapp-providers.manage');

        $this->validate(['testPhone' => ['required', 'string', 'max:30']], [], ['testPhone' => __('WhatsApp number')]);

        $result = $providers->sendTest($this->provider($providerUlid), $this->testPhone);

        $result->success
            ? $this->notify('success', __('Test message sent.'))
            : $this->notify('error', __('Test failed: :error', ['error' => (string) $result->error]));
    }

    public function render(): View
    {
        $driver = WhatsAppDriver::tryFrom($this->driver) ?? WhatsAppDriver::Fonnte;

        return view('livewire.whats-app-provider-manager', [
            'providers' => WhatsAppProvider::query()->platform()->orderByDesc('is_active')->orderBy('name')->get(),
            'drivers' => WhatsAppDriver::cases(),
            'fields' => $driver->credentialFields(),
            'webhookUrl' => $driver->supportsStatusWebhook() ? route('webhooks.whatsapp', ['driver' => $driver->value]) : null,
        ]);
    }

    private function provider(string $providerUlid): WhatsAppProvider
    {
        return WhatsAppProvider::query()->platform()->where('ulid', $providerUlid)->firstOrFail();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingUlid = null;
        $this->driver = WhatsAppDriver::Fonnte->value;
        $this->name = '';
        $this->ratePerMinute = 20;
        $this->credentials = [];
        $this->storedSecrets = [];
    }
}
