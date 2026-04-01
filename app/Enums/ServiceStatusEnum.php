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
}
