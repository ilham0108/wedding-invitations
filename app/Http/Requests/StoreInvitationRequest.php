<?php

namespace App\Http\Requests;

use App\Enums\EventType;
use App\Enums\TemplateStatus;
use App\Models\Invitation;
use App\Models\InvitationTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared by the InvitationCreator Livewire wizard and the future API (rules.md section 74).
 */
class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invitation::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'event_type' => ['required', Rule::enum(EventType::class)],
            'template' => ['required', 'string', Rule::exists(InvitationTemplate::class, 'slug')->where('status', TemplateStatus::Published->value)->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:120'],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:160'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
