<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Services\AuditLogger;
use App\Services\FaviconService;
use App\Services\LogoService;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Admin system settings (/admin/settings).
 */
class SystemSettingsForm extends Component
{
    use Notifies, WithFileUploads;

    /** @var array<string, mixed> */
    public array $settings = [];

    /** New logo; saved as soon as it is chosen. */
    public ?TemporaryUploadedFile $logo = null;

    /** New site icon; saved as soon as it is chosen. */
    public ?TemporaryUploadedFile $favicon = null;

    public function mount(SystemSettings $systemSettings): void
    {
        Gate::authorize('settings.manage');

        $this->settings = $systemSettings->all();
    }

    public function save(SystemSettings $systemSettings, AuditLogger $auditLogger): void
    {
        Gate::authorize('settings.manage');

        $rules = collect(SystemSettings::rules())->mapWithKeys(fn (array $rule, string $key): array => ["settings.{$key}" => $rule])->all();
        $attributes = collect(SystemSettings::DEFINITIONS)->mapWithKeys(fn (array $definition, string $key): array => ["settings.{$key}" => mb_strtolower(__($definition['label']))])->all();

        $validated = $this->validate($rules, [], $attributes)['settings'];

        $systemSettings->save($validated, Auth::user(), $auditLogger);
        $this->settings = $systemSettings->all();

        $this->notify('success', __('Settings saved.'));
    }

    public function updatedLogo(LogoService $logoService): void
    {
        Gate::authorize('settings.manage');

        $this->validate(['logo' => ['required', ...LogoService::rules()]], [], ['logo' => __('logo')]);

        $logoService->replace($this->logo, Auth::user());
        $this->logo = null;

        $this->notify('success', __('Logo saved.'));
    }

    public function removeLogo(LogoService $logoService): void
    {
        Gate::authorize('settings.manage');

        $logoService->replace(null, Auth::user());

        $this->notify('success', __('Logo removed.'));
    }

    public function updatedFavicon(FaviconService $faviconService): void
    {
        Gate::authorize('settings.manage');

        $this->validate(['favicon' => ['required', ...FaviconService::rules()]], [], ['favicon' => __('site icon')]);

        $faviconService->replace($this->favicon, Auth::user());
        $this->favicon = null;

        $this->notify('success', __('Site icon saved.'));
    }

    public function removeFavicon(FaviconService $faviconService): void
    {
        Gate::authorize('settings.manage');

        $faviconService->replace(null, Auth::user());

        $this->notify('success', __('Site icon removed.'));
    }

    public function render(FaviconService $faviconService, LogoService $logoService): View
    {
        return view('livewire.system-settings-form', [
            'logoUrl' => $logoService->url(),
            'hasUploadedLogo' => $logoService->exists(),
            'faviconUrl' => $faviconService->url(192),
            'definitions' => SystemSettings::DEFINITIONS,
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
