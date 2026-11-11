<?php

declare(strict_types=1);

namespace App\Enums;

enum RegistrationStatus: int
{
    case Open = 0;
    case Invite = 1;
    case Closed = 2;

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Invite => 'Invite',
            self::Closed => 'Closed',
        };
    }

    /**
     * Colour classes for the status pill. Kept here (and listed as a Tailwind source in
     * resources/css/app.css) so Blade and the dashboard script share one definition.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'border border-emerald-500/30 bg-emerald-600 text-white shadow-sm dark:border-emerald-300/20 dark:bg-emerald-500 dark:text-slate-950',
            self::Invite => 'border border-amber-500/30 bg-amber-500 text-slate-950 shadow-sm dark:border-amber-200/20 dark:bg-amber-400 dark:text-slate-950',
            self::Closed => 'border border-rose-500/30 bg-rose-600 text-white shadow-sm dark:border-rose-200/20 dark:bg-rose-500 dark:text-white',
        };
    }

    /**
     * Badge classes keyed by enum value, for scripts that render the pill client-side.
     *
     * @return array<int, string>
     */
    public static function badgeClassMap(): array
    {
        $map = [];
        foreach (self::cases() as $case) {
            $map[$case->value] = $case->badgeClasses();
        }

        return $map;
    }
}
