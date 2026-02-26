<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Propaganistas\LaravelDisposableEmail\Facades\DisposableDomains;

class ValidEmailDomain implements ValidationRule
{
    /**
     * Common temporary/disposable email patterns
     */
    private const SUSPICIOUS_PATTERNS = [
        'temp',
        'temporary',
        'disposable',
        'throwaway',
        'fake',
        'trash',
        'spam',
        'guerrilla',
        '10minute',
        'minutemail',
        'tempmail',
        'guerrillamail',
        'mailinator',
        'yopmail',
        'maildrop',
        'trashmail',
        'getnada',
        'throwawaymail',
        'sharklasers',
        'guerrillamail',
        'grr.la',
    ];

    /**
     * Additional hardcoded disposable domains list
     */
    private const BLOCKED_DOMAINS = [
        '10minutemail.com',
        'guerrillamail.com',
        'mailinator.com',
        'maildrop.cc',
        'temp-mail.org',
        'tempmail.com',
        'throwawaymail.com',
        'yopmail.com',
        'getnada.com',
        'sharklasers.com',
        'guerrillamail.info',
        'guerrillamail.biz',
        'guerrillamail.de',
        'grr.la',
        'guerrillamail.net',
        'guerrillamail.org',
        'guerrillamail.com',
        'spam4.me',
        'trashmail.com',
        'dispostable.com',
        'fakeinbox.com',
        'tmpeml.info',
        'tempinbox.com',
        'mytemp.email',
        'mohmal.com',
        'emailondeck.com',
        'throwam.com',
        'mintemail.com',
        'spamgourmet.com',
        'mailnesia.com',
        'trashmail.net',
        'trash-mail.com',
        'emailtemporanea.com',
        'anonbox.net',
        'anonymbox.com',
        'discard.email',
        'spambox.us',
        'trbvm.com',
        'emailna.co',
        'my10minutemail.com',
    ];

    /**
     * Validate the email domain
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value) || ! is_string($value)) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        // Extract domain from email
        $parts = explode('@', $value);
        if (count($parts) !== 2) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        $localPart = trim($parts[0]);
        $domain = strtolower(trim($parts[1]));

        // Validate local part is not empty
        if (empty($localPart)) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        // Validate domain is not empty
        if (empty($domain)) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        // Check 1: Use the disposable email package
        if (! DisposableDomains::isNotDisposable($value)) {
            Log::warning('Disposable email attempt blocked (package detection)', [
                'email' => $value,
                'domain' => $domain,
            ]);
            $fail('Temporary or disposable email addresses are not allowed.');

            return;
        }

        // Check 2: Check against our hardcoded blacklist
        if (in_array($domain, self::BLOCKED_DOMAINS, true)) {
            Log::warning('Disposable email attempt blocked (hardcoded blacklist)', [
                'email' => $value,
                'domain' => $domain,
            ]);
            $fail('Temporary or disposable email addresses are not allowed.');

            return;
        }

        // Check 3: Check for suspicious patterns in domain name
        foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
            if (str_contains($domain, $pattern)) {
                Log::warning('Disposable email attempt blocked (pattern match)', [
                    'email' => $value,
                    'domain' => $domain,
                    'pattern' => $pattern,
                ]);
                $fail('Temporary or disposable email addresses are not allowed.');

                return;
            }
        }

        // Check 4: Validate domain has valid DNS records (MX or A record)
        if (! $this->validateDnsRecords($domain)) {
            Log::warning('Email domain has no valid DNS records', [
                'email' => $value,
                'domain' => $domain,
            ]);
            $fail('The email domain does not appear to be valid or reachable.');

            return;
        }

        // Check 5: Block common free email services with plus addressing abuse
        // (optional - you may want to comment this out if you want to allow Gmail, etc.)
        // if ($this->hasSuspiciousPlusAddressing($value)) {
        //     Log::warning('Suspicious plus addressing detected', [
        //         'email' => $value,
        //         'domain' => $domain,
        //     ]);
        //     $fail('This email format is not allowed.');
        //     return;
        // }
    }

    /**
     * Validate that the domain has proper DNS records
     */
    private function validateDnsRecords(string $domain): bool
    {
        // Check for MX records (primary email validation)
        if (@checkdnsrr($domain, 'MX')) {
            return true;
        }

        // Fall back to A record check (some domains use A records for email)
        if (@checkdnsrr($domain, 'A')) {
            return true;
        }

        return false;
    }

    /**
     * Check for suspicious plus addressing patterns
     * Some users abuse plus addressing to create multiple accounts
     *
     * @phpstan-ignore method.unused
     */
    private function hasSuspiciousPlusAddressing(string $email): bool
    {
        $parts = explode('@', $email);
        $localPart = $parts[0];

        // Check if contains + with suspicious patterns
        if (str_contains($localPart, '+')) {
            // You can add more sophisticated checks here
            // For now, just log it but don't block
            return false;
        }

        return false;
    }
}
