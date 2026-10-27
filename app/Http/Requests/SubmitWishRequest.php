<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public guestbook form. "website" is a honeypot that real guests never fill in.
 */
class SubmitWishRequest extends FormRequest
{
    protected $errorBag = 'wishes';

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
            'name' => ['required_without:token', 'nullable', 'string', 'max:80'],
            'message' => ['required', 'string', 'max:500'],
            'website' => ['prohibited'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return url()->previous().'#wishes-form';
    }
}
