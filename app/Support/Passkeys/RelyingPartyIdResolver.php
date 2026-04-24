<?php

declare(strict_types=1);

namespace App\Support\Passkeys;

use Illuminate\Http\Request;

final class RelyingPartyIdResolver
{
    public static function resolve(?Request $request = null): string
    {
        $request ??= request();

        $candidates = [
            self::firstHost((string) $request->header('X-Forwarded-Host', '')),
            self::firstHost((string) $request->header('X-Original-Host', '')),
            (string) config('passkeys.relying_party.id', ''),
            (string) $request->getHost(),
        ];

        foreach ($candidates as $candidate) {
            if (self::isValidRelyingPartyId($candidate)) {
                return $candidate;
            }
        }

        return 'localhost';
    }

    private static function firstHost(string $value): string
    {
        $host = trim(explode(',', $value)[0] ?? '');

        if ($host === '') {
            return '';
        }

        // Strip optional port suffix.
        return trim((string) preg_replace('/:\d+$/', '', $host));
    }

    private static function isValidRelyingPartyId(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || filter_var($value, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        if ($value === 'localhost') {
            return true;
        }

        return str_contains($value, '.');
    }
}
