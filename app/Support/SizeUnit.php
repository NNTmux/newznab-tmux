<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Converts release-size settings between bytes (storage format) and the
 * human-selectable units (MB / GB) shown on the admin settings pages.
 */
class SizeUnit
{
    public const int MB = 1048576;

    public const int GB = 1073741824;

    public const array UNITS = ['MB', 'GB'];

    /**
     * Site settings whose values are release sizes stored in bytes.
     *
     * @var list<string>
     */
    public const array SITE_SIZE_SETTINGS = [
        'minsizetoformrelease',
        'maxsizetoformrelease',
        'minsizetopostprocess',
        'maxsizetopostprocess',
        'minsizetoprocessnfo',
        'maxsizetoprocessnfo',
    ];

    /**
     * Convert a value expressed in the given unit to bytes.
     *
     * Empty and non-positive values map to 0 ("disabled" semantics).
     */
    public static function toBytes(int|float|string|null $value, string $unit): int
    {
        $multiplier = match (strtoupper($unit)) {
            'MB' => self::MB,
            'GB' => self::GB,
            default => throw new InvalidArgumentException("Unsupported size unit [$unit]."),
        };

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) round((float) $value * $multiplier));
    }

    /**
     * Split a byte count into a value + unit pair for display.
     *
     * GB is preferred when the value divides evenly into gibibytes, otherwise
     * MB is used (rounded to two decimals when not evenly divisible).
     *
     * @return array{value: int|float, unit: string}
     */
    public static function fromBytes(int|float|string|null $bytes): array
    {
        $bytes = is_numeric($bytes) ? (int) $bytes : 0;

        if ($bytes <= 0) {
            return ['value' => 0, 'unit' => 'MB'];
        }

        if ($bytes % self::GB === 0) {
            return ['value' => intdiv($bytes, self::GB), 'unit' => 'GB'];
        }

        if ($bytes % self::MB === 0) {
            return ['value' => intdiv($bytes, self::MB), 'unit' => 'MB'];
        }

        return ['value' => round($bytes / self::MB, 2), 'unit' => 'MB'];
    }
}
