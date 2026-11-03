<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\ConnectionInterface;

/**
 * Runs an ALTER TABLE on MySQL/MariaDB without letting it stall the table.
 *
 * Every ALTER briefly needs an exclusive metadata lock. While it waits for a long transaction to end,
 * every later query on that table queues behind it, and lock_wait_timeout can be a day (86400 s) in
 * production. Waiting only a few seconds lets the migration fail and be rerun instead. MariaDB's
 * max_statement_time is lifted for the ALTER so a long index build is not cut off.
 */
final class OnlineSchemaChange
{
    public const int LOCK_WAIT_TIMEOUT_SECONDS = 10;

    /** @var array<string, int> */
    private const array SESSION_LIMITS = [
        'lock_wait_timeout' => self::LOCK_WAIT_TIMEOUT_SECONDS,
        'max_statement_time' => 0,
    ];

    public static function run(ConnectionInterface $connection, string $alterSql): void
    {
        /** @var array<string, string> $previous */
        $previous = [];

        try {
            foreach (self::SESSION_LIMITS as $variable => $value) {
                $current = self::sessionValue($connection, $variable);
                if ($current === null) {
                    continue;
                }

                $previous[$variable] = $current;
                $connection->statement(sprintf('SET SESSION %s = %d', $variable, $value));
            }

            $connection->statement($alterSql);
        } finally {
            foreach ($previous as $variable => $value) {
                $connection->statement(sprintf('SET SESSION %s = %s', $variable, $value));
            }
        }
    }

    /**
     * Returns null when the server has no such variable (max_statement_time is MariaDB only).
     */
    private static function sessionValue(ConnectionInterface $connection, string $variable): ?string
    {
        $row = $connection->selectOne(sprintf("SHOW SESSION VARIABLES LIKE '%s'", $variable));
        $value = is_object($row) ? ($row->Value ?? null) : null;

        return is_numeric($value) ? (string) $value : null;
    }
}
