<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Current health of a monitored service (stored in service_statuses.status).
 */
enum ServiceStatusEnum: string
{
    case Operational = 'operational';
    case Degraded = 'degraded';
    case PartialOutage = 'partial_outage';
    case MajorOutage = 'major_outage';
    case Maintenance = 'maintenance';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::Degraded => 'Degraded',
            self::PartialOutage => 'Partial outage',
            self::MajorOutage => 'Major outage',
            self::Maintenance => 'Maintenance',
        };
    }

    /**
     * Higher value = worse condition for aggregation (worst wins).
     */
    public function severity(): int
    {
        return match ($this) {
            self::Operational => 0,
            self::Degraded => 1,
            self::Maintenance => 2,
            self::PartialOutage => 3,
            self::MajorOutage => 4,
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
     * Colour classes for the status pill. Kept here (and listed as a Tailwind source in
     * resources/css/app.css) so Blade and the dashboard script share one definition.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Operational => 'bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200',
            self::Degraded => 'bg-warning-100 dark:bg-warning-900 text-warning-800 dark:text-warning-200',
            self::Maintenance => 'bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200',
            self::PartialOutage => 'bg-orange-100 dark:bg-orange-900 text-orange-800 dark:text-orange-200',
            self::MajorOutage => 'bg-danger-100 dark:bg-danger-900 text-danger-800 dark:text-danger-200',
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
