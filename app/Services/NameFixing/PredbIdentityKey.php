<?php

declare(strict_types=1);

namespace App\Services\NameFixing;

final class PredbIdentityKey
{
    public static function normalize(string $value): ?string
    {
        if ($value === '' || strlen($value) > 512 || ! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }
        $value = \Normalizer::normalize($value, \Normalizer::FORM_C) ?: $value;
        $value = mb_strtolower(trim($value), 'UTF-8');

        return preg_replace('/[._\s]+/u', '.', $value);
    }
}
