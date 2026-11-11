<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentImpactEnum: string
{
    case None = 'none';
    case Minor = 'minor';
    case Major = 'major';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::None => 'None',
            self::Minor => 'Minor',
            self::Major => 'Major',
            self::Critical => 'Critical',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Colour classes for the impact pill. Kept here (and listed as a Tailwind source in
     * resources/css/app.css) so Blade and the dashboard script share one definition.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::None => 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200',
            self::Minor => 'bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200',
            self::Major => 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200',
            self::Critical => 'bg-danger-100 dark:bg-danger-900 text-danger-800 dark:text-danger-200',
        };
    }

    /**
     * Badge classes keyed by enum value, for scripts that render the pill client-side.
     *
     * @return array<string, string>
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
