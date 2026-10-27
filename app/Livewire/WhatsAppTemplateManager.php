<?php

namespace App\Livewire;

use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\MessageRenderer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Client message templates with variable insertion (rules.md section 40).
 */
class WhatsAppTemplateManager extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?string $editingUlid = null;

    public string $name = '';

    public string $body = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', WhatsAppCampaign::class);
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->editingUlid = null;
        $this->name = '';
        $this->body = MessageRenderer::defaultTemplate();
        $this->showForm = true;
    }

    public function edit(string $templateUlid): void
    {
        $template = $this->template($templateUlid);

        $this->resetValidation();
        $this->editingUlid = $template->ulid;
        $this->name = $template->name;
        $this->body = $template->body;
        $this->showForm = true;
    }

    public function save(MessageRenderer $renderer): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:'.MessageRenderer::MAX_LENGTH],
        ], [], ['body' => mb_strtolower(__('Message'))]);

        if ($unknown = $renderer->unknownVariables($this->body)) {
            throw ValidationException::withMessages(['body' => __('Unknown variables: :list.', ['list' => implode(', ', $unknown)])]);
        }

        $template = $this->editingUlid ? $this->template($this->editingUlid) : new WhatsAppTemplate;
        $template->fill(['name' => trim($this->name), 'body' => $this->body]);

        if (! $template->exists) {
            $template->user()->associate(Auth::user());
        }

        $template->save();

        $this->showForm = false;
    }

    public function delete(string $templateUlid): void
    {
        $this->template($templateUlid)->delete();
    }

    public function render(): View
    {
        return view('livewire.whats-app-template-manager', [
            'templates' => Auth::user()->whatsappTemplates()->get(['id', 'ulid', 'user_id', 'name', 'body', 'updated_at']),
            'variables' => MessageRenderer::variables(),
        ]);
    }

    private function template(string $templateUlid): WhatsAppTemplate
    {
        $template = WhatsAppTemplate::query()->where('ulid', $templateUlid)->firstOrFail();
        Gate::authorize('update', $template);

        return $template;
    }
}
