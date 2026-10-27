<?php

namespace App\Http\Requests;

use App\Enums\RsvpAttendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public RSVP form. "website" is a honeypot that real guests never fill in.
 */
class SubmitRsvpRequest extends FormRequest
{
    protected $errorBag = 'rsvp';

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
            'token' => ['nullable', 'string', 'size:32'],
            'name' => ['required_without:token', 'nullable', 'string', 'max:120'],
            'attendance' => ['required', Rule::enum(RsvpAttendance::class)],
            'number_of_guests' => ['nullable', 'required_if:attendance,attending', 'integer', 'min:1', 'max:20'],
            'meal_preference' => ['nullable', 'string', 'max:60'],
            'message' => ['nullable', 'string', 'max:500'],
            'website' => ['prohibited'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return url()->previous().'#rsvp-form';
    }
}
