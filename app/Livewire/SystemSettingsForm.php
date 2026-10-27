<?php

namespace App\Livewire;

use App\Livewire\Concerns\Notifies;
use App\Services\AuditLogger;
use App\Services\SystemSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Admin system settings (/admin/settings).
 */
class SystemSettingsForm extends Component
{
    use Notifies;

    /** @var array<string, mixed> */
    public array $settings = [];

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

    public function render(): View
    {
        return view('livewire.system-settings-form', [
            'definitions' => SystemSettings::DEFINITIONS,
            'timezones' => timezone_identifiers_list(),
        ]);
    }
}
