<?php

namespace App\Rules;

use App\Support\AudioFormat;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * An MP3 or audio-only M4A file, judged by its contents (AudioFormat), not by its name.
 */
class AudioFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || AudioFormat::detect((string) $value->getRealPath(), (string) $value->getMimeType()) === null) {
            $fail(__('The :attribute must be an MP3 or M4A audio file.'));
        }
    }
}
