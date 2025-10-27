<?php

namespace App\Rules;

use App\Services\TurnstileService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TurnstileRule implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail('Please complete the captcha verification.');

            return;
        }

        if (! TurnstileService::verify($value)) {
            $fail('The captcha verification failed. Please try again.');
        }
    }
}
