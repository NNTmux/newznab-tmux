<?php

declare(strict_types=1);

namespace App\Services\NameFixing\Extractors;

final class ObfuscatedSubjectExtractor
{
    /**
     * @var list<string>
     */
    private const array PREFIX_PATTERNS = [
        '/^\s*N:\/NZB\s*\[\d+\/\d+\]\s*-\s*/i',
        '/^\s*N[\s._:-]*NZB[\s._-]*\[\d+(?:[\/_]\d+)?\][\s._-]*-\s*/i',
        '/^\s*\[[^\]]{2,80}\]\s*/',
        '/^\s*(?:re\s*)?posted\s+by\s+[^-]{2,120}\s*-\s*/i',
    ];

    /**
     * @var list<string>
     */
    private const array TRAILING_ARCHIVE_PATTERNS = [
        '/\.part\d+\.rar$/i',
        '/\.r\d{2,4}$/i',
        '/\.7z\.\d{2,4}$/i',
        '/\.7z$/i',
        '/\.rar$/i',
        '/\.par2?$/i',
        '/\.part$/i',
        '/\.vol\d+[+\-]\d+\.par2?$/i',
        '/\.\d{3}$/',
    ];

    public function extract(string $value): ?string
    {
        $original = trim($value);
        if ($original === '') {
            return null;
        }

        $normalized = $original;
        $looksObfuscated = false;
        foreach (self::PREFIX_PATTERNS as $pattern) {
            $updated = preg_replace($pattern, '', $normalized) ?? $normalized;
            if ($updated !== $normalized) {
                $looksObfuscated = true;
                $normalized = $updated;
            }
        }

        if (preg_match('/"([^"]{3,240})"/', $normalized, $quoted) === 1) {
            $normalized = $quoted[1];
            $looksObfuscated = true;
        } elseif (preg_match("/'([^']{3,240})'/", $normalized, $quoted) === 1) {
            $normalized = $quoted[1];
            $looksObfuscated = true;
        }

        if ($looksObfuscated) {
            foreach (self::TRAILING_ARCHIVE_PATTERNS as $pattern) {
                $normalized = preg_replace($pattern, '', $normalized) ?? $normalized;
            }
        }

        $normalized = str_replace(['_', '+'], [' ', ' '], $normalized);
        $normalized = preg_replace('/\.(?=[A-Za-z0-9])/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s{2,}/', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized, " \t\n\r\0\x0B\"'`-_.");
        $normalized = $this->toReadableTitle($normalized);

        if ($normalized === '' || $normalized === $original) {
            return null;
        }

        return $normalized;
    }

    private function toReadableTitle(string $value): string
    {
        // If the candidate looks fully lowercase, present a cleaner title-cased
        // variant for UI/searchname while preserving separators and numbers.
        if (preg_match('/\p{Lu}/u', $value) === 1) {
            return $value;
        }

        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }
}
