<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Returns approximate row counts for large tables using
 * information_schema.TABLES.TABLE_ROWS so we avoid COUNT(*) full scans
 * on the hottest dashboard tiles.
 *
 * Falls back to an exact COUNT(*) when:
 *  - the connection isn't MySQL/MariaDB, or
 *  - the approximation is unavailable / reports 0 (common for tiny tables).
 */
final class ApproximateRowCount
{
    /**
     * Approximate count for a single table.
     */
    public static function for(string $table, ?string $connection = null): int
    {
        $conn = DB::connection($connection);

        if (! self::supportsInformationSchema($conn)) {
            return (int) $conn->table($table)->count();
        }

        try {
            $row = $conn->selectOne(
                'SELECT TABLE_ROWS AS approx FROM information_schema.tables WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
                [$table]
            );
        } catch (\Throwable) {
            $row = null;
        }

        $approx = $row?->approx ?? null;

        if ($approx === null || (int) $approx <= 0) {
            // Fall back to exact count on tables InnoDB hasn't analyzed yet
            // or that are small enough that the approximation is meaningless.
            return (int) $conn->table($table)->count();
        }

        return (int) $approx;
    }

    private static function supportsInformationSchema(ConnectionInterface $conn): bool
    {
        $driver = method_exists($conn, 'getDriverName') ? $conn->getDriverName() : null;

        return in_array($driver, ['mysql', 'mariadb'], true);
    }
}

