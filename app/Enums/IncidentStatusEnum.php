<?php

declare(strict_types=1);

namespace App\Enums;

enum IncidentStatusEnum: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Investigating => 'Investigating',
            self::Identified => 'Identified',
            self::Monitoring => 'Monitoring',
            self::Resolved => 'Resolved',
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
     * Colour classes for the incident status pill (listed as a Tailwind source in resources/css/app.css).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Investigating => 'bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-200',
            self::Identified => 'bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200',
            self::Monitoring => 'bg-cyan-100 dark:bg-cyan-900 text-cyan-800 dark:text-cyan-200',
            self::Resolved => 'bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200',
        };
    }
}
