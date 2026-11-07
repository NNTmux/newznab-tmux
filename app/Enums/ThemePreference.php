<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Light/dark mode preference stored in users.theme_preference.
 */
enum ThemePreference: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    /**
     * Matches the users.theme_preference column default.
     */
    public static function default(): self
    {
        return self::Light;
    }

    /**
     * Resolve a stored value, falling back to the default for unknown input.
     */
    public static function fromPreference(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::default();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $preference): string => $preference->value, self::cases());
    }
}
