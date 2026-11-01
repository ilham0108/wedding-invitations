<?php

namespace App\Rules;

use App\Support\FontFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A WOFF2, WOFF, TTF or OTF font file, judged by its contents (FontFormat), not by its name.
 */
class FontFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || FontFormat::detect((string) $value->getRealPath()) === null) {
            $fail(__('The :attribute must be a WOFF2, WOFF, TTF or OTF font file.'));
        }
    }
}
