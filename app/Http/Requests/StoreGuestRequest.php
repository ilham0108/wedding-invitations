<?php

namespace App\Http\Requests;

use App\Services\Guest\GuestService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Guest fields (rules.md section 32). Shared by the GuestTable Livewire form and a future API.
 * Per-invitation phone uniqueness is checked by the caller, which knows the invitation.
 */
class StoreGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (! GuestService::isValidPhone(GuestService::normalizePhone((string) $value))) {
                    $fail(__('Phone number format is invalid.'));
                }
            }],
            'email' => ['nullable', 'email', 'max:255'],
            'group' => ['nullable', 'string', 'max:60'],
            'number_of_guests' => ['required', 'integer', 'min:1', 'max:20'],
            'table_name' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
