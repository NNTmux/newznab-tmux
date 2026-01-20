<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class PasswordBreachService
{
    /**
     * Minimum number of times a password must appear in breaches to be considered compromised.
     */
    protected int $threshold;

    public function __construct(int $threshold = 1)
    {
        $this->threshold = $threshold;
    }

    /**
     * Check if a password has been found in data breaches.
     *
     * This method uses Laravel's Password validation rule with uncompromised() which queries
     * the Have I Been Pwned API using k-Anonymity (only first 5 chars of SHA-1 hash are sent).
     *
     * @param  string  $password  The password to check
     * @return bool True if the password has been found in breaches, false otherwise
     */
    public function isPasswordBreached(string $password): bool
    {
        try {
            $validator = Validator::make(
                ['password' => $password],
                ['password' => Password::min(1)->uncompromised($this->threshold)]
            );

            // If validation fails, the password is compromised
            return $validator->fails();
        } catch (\Exception $e) {
            // Log the error but don't block login (fail open)
            Log::error('Password breach check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
