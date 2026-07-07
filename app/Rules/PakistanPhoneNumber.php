<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PakistanPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^03[0-9]{9}$/', $value)) {
            $fail('The :attribute must be a valid Pakistani mobile number (e.g. 03001234567).');
        }
    }
}
