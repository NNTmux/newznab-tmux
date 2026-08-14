<?php

declare(strict_types=1);

namespace App\Support;

final class BookIsbn
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $isbn = strtoupper((string) preg_replace('/[^0-9X]/i', '', $value));

        return match (strlen($isbn)) {
            10 => self::isValidIsbn10($isbn) ? $isbn : null,
            13 => self::isValidIsbn13($isbn) ? $isbn : null,
            default => null,
        };
    }

    public static function equivalent(?string $left, ?string $right): bool
    {
        $leftIdentifiers = self::equivalents($left);
        $rightIdentifiers = self::equivalents($right);

        return $leftIdentifiers !== [] && array_intersect($leftIdentifiers, $rightIdentifiers) !== [];
    }

    /**
     * @return list<string>
     */
    public static function equivalents(?string $value): array
    {
        $isbn = self::normalize($value);
        if ($isbn === null) {
            return [];
        }

        $identifiers = [$isbn];
        $converted = strlen($isbn) === 10 ? self::toIsbn13($isbn) : self::toIsbn10($isbn);
        if ($converted !== null) {
            $identifiers[] = $converted;
        }

        sort($identifiers);

        return array_values(array_unique($identifiers));
    }

    public static function toIsbn13(?string $value): ?string
    {
        $isbn10 = self::normalize($value);
        if ($isbn10 === null || strlen($isbn10) !== 10) {
            return null;
        }

        $body = '978'.substr($isbn10, 0, 9);
        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += ((int) $body[$index]) * ($index % 2 === 0 ? 1 : 3);
        }

        return $body.(string) ((10 - ($sum % 10)) % 10);
    }

    public static function toIsbn10(?string $value): ?string
    {
        $isbn13 = self::normalize($value);
        if ($isbn13 === null || strlen($isbn13) !== 13 || ! str_starts_with($isbn13, '978')) {
            return null;
        }

        $body = substr($isbn13, 3, 9);
        $sum = 0;
        for ($index = 0; $index < 9; $index++) {
            $sum += ((int) $body[$index]) * (10 - $index);
        }
        $check = (11 - ($sum % 11)) % 11;

        return $body.($check === 10 ? 'X' : (string) $check);
    }

    private static function isValidIsbn10(string $isbn): bool
    {
        if (preg_match('/^\d{9}[\dX]$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 10; $index++) {
            $digit = $isbn[$index] === 'X' ? 10 : (int) $isbn[$index];
            $sum += $digit * (10 - $index);
        }

        return $sum % 11 === 0;
    }

    private static function isValidIsbn13(string $isbn): bool
    {
        if (preg_match('/^97[89]\d{10}$/', $isbn) !== 1) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 13; $index++) {
            $sum += ((int) $isbn[$index]) * ($index % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }
}
