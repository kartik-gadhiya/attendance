<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TimeFormatRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure(string): \Illuminate\Translation\PotentiallyTranslatedString $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Accept both H:i and H:i:s formats
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            $fail("The {$attribute} field must be in HH:MM or HH:MM:SS format.");
        }
    }
}
