<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Accent color schemes a user can pick. Each case has a matching
 * `[data-color-scheme="…"]` token block in resources/css/app.css.
 */
enum ColorScheme: string
{
    case Blue = 'blue';
    case Indigo = 'indigo';
    case Cyan = 'cyan';
    case Teal = 'teal';
    case Emerald = 'emerald';
    case Violet = 'violet';
    case Pink = 'pink';
    case Rose = 'rose';
    case Red = 'red';
    case Orange = 'orange';
    case Amber = 'amber';

    public static function default(): self
    {
        return self::Blue;
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
        return array_map(static fn (self $scheme): string => $scheme->value, self::cases());
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Static Tailwind class for the picker swatch (scanned via @source in app.css).
     */
    public function swatchClass(): string
    {
        return match ($this) {
            self::Blue => 'bg-blue-600',
            self::Indigo => 'bg-indigo-600',
            self::Cyan => 'bg-cyan-600',
            self::Teal => 'bg-teal-600',
            self::Emerald => 'bg-emerald-600',
            self::Violet => 'bg-violet-600',
            self::Pink => 'bg-pink-600',
            self::Rose => 'bg-rose-600',
            self::Red => 'bg-red-600',
            self::Orange => 'bg-orange-600',
            self::Amber => 'bg-amber-600',
        };
    }

    /**
     * Page background painted before CSS loads. Mirrors --surface-body / --surface-body-dark.
     */
    public function canvas(bool $dark): string
    {
        return match ($this) {
            self::Blue => $dark ? '#0f172a' : '#f8fafc',
            self::Indigo => $dark ? '#0b1024' : '#eef2ff',
            self::Cyan => $dark ? '#06191d' : '#ecfeff',
            self::Teal => $dark ? '#061a18' : '#f0fdfa',
            self::Emerald => $dark ? '#071a12' : '#f0fdf4',
            self::Violet => $dark ? '#120b20' : '#faf5ff',
            self::Pink => $dark ? '#200a17' : '#fdf2f8',
            self::Rose => $dark ? '#1f0a12' : '#fff1f2',
            self::Red => $dark ? '#200a0a' : '#fef2f2',
            self::Orange => $dark ? '#1f1007' : '#fff7ed',
            self::Amber => $dark ? '#1c1304' : '#fffbeb',
        };
    }

    /**
     * Body text color painted before CSS loads.
     */
    public function foreground(bool $dark): string
    {
        if (! $dark) {
            return '#1e293b';
        }

        return match ($this) {
            self::Blue => '#e2e8f0',
            self::Indigo => '#c7d2fe',
            self::Cyan => '#cffafe',
            self::Teal => '#ccfbf1',
            self::Emerald => '#d1fae5',
            self::Violet => '#e9d5ff',
            self::Pink => '#fce7f3',
            self::Rose => '#fecdd3',
            self::Red => '#fee2e2',
            self::Orange => '#ffedd5',
            self::Amber => '#fde68a',
        };
    }
}
