<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use Normalizer;

final class FilenameSanitizer
{
    private const int MAX_FILENAME_LENGTH = 200;

    /**
     * @var list<string>
     */
    private const array UNICODE_PATH_SEPARATORS = [
        '⁄',
        '∕',
        '⧸',
        '／',
        '＼',
        '﹨',
        '⧵',
        '⟋',
        '⟍',
        '╱',
        '╲',
    ];

    public static function sanitize(?string $name, string $fallback = 'download'): string
    {
        $sanitized = self::normalize($name);

        if ($sanitized === '') {
            return self::safeFallback($fallback);
        }

        return self::finalize($sanitized, $fallback);
    }

    public static function asciiFallback(?string $name, string $fallback = 'download'): string
    {
        $sanitized = self::sanitize($name, $fallback);
        $ascii = Str::ascii($sanitized);
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii) ?? $ascii;
        $ascii = preg_replace('/_+/', '_', $ascii) ?? $ascii;

        return self::finalize($ascii, $fallback);
    }

    private static function normalize(?string $name): string
    {
        $normalized = trim((string) $name);

        if ($normalized === '') {
            return '';
        }

        if (class_exists(Normalizer::class)) {
            $candidate = Normalizer::normalize($normalized, Normalizer::FORM_KC);
            if (is_string($candidate)) {
                $normalized = $candidate;
            }
        }

        $normalized = str_replace(self::UNICODE_PATH_SEPARATORS, '/', $normalized);
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', '', $normalized) ?? $normalized;
        $normalized = str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '_', $normalized);
        $normalized = preg_replace('/[\s,]+/u', '_', $normalized) ?? $normalized;
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;

        return self::trimFilename($normalized);
    }

    private static function finalize(string $name, string $fallback): string
    {
        $name = mb_substr($name, 0, self::MAX_FILENAME_LENGTH);
        $name = self::trimFilename($name);

        if ($name === '' || $name === '.' || $name === '..') {
            return self::safeFallback($fallback);
        }

        return $name;
    }

    private static function safeFallback(string $fallback): string
    {
        $safeFallback = Str::ascii($fallback);
        $safeFallback = preg_replace('/[^A-Za-z0-9._-]+/', '_', $safeFallback) ?? $safeFallback;
        $safeFallback = preg_replace('/_+/', '_', $safeFallback) ?? $safeFallback;
        $safeFallback = mb_substr($safeFallback, 0, self::MAX_FILENAME_LENGTH);
        $safeFallback = self::trimFilename($safeFallback);

        if ($safeFallback === '' || $safeFallback === '.' || $safeFallback === '..') {
            return 'download';
        }

        return $safeFallback;
    }

    private static function trimFilename(string $name): string
    {
        return ltrim(trim($name, " \t\n\r\0\x0B._-"), '.');
    }
}
