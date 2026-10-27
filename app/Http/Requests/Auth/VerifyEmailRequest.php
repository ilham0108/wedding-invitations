<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Auth\EmailVerificationRequest;

/**
 * Verification links carry the public ULID instead of the internal user id.
 */
class VerifyEmailRequest extends EmailVerificationRequest
{
    public function authorize(): bool
    {
        if (! hash_equals((string) $this->user()->getRouteKey(), (string) $this->route('id'))) {
            return false;
        }

        return hash_equals(sha1($this->user()->getEmailForVerification()), (string) $this->route('hash'));
    }
}
