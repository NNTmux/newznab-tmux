<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small UTF-8 conversion helper used in hot header-processing paths.
 */
final class Utf8
{
    /** @var list<string>|null */
    private static ?array $encodings = null;

    public static function clean(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', self::encodings());
    }

    /**
     * @return list<string>
     */
    private static function encodings(): array
    {
        if (self::$encodings === null) {
            self::$encodings = mb_list_encodings();
        }

        return self::$encodings;
    }
}

