<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;

/**
 * Small building blocks for hand-written SQL on the hot ingestion and release paths, where the
 * query builder's per-call compile and hydration overhead is not wanted.
 */
final class RawSql
{
    /**
     * MariaDB added INSERT ... RETURNING in 10.5; MySQL has no equivalent.
     */
    private const string INSERT_RETURNING_MIN_MARIADB_VERSION = '10.5.0';

    /** @var array<string, bool> Keyed by connection name */
    private static array $insertReturningSupport = [];

    /**
     * Comma-separated placeholders for an IN (...) list or a multi-row VALUES list.
     */
    public static function placeholders(int $count, string $placeholder = '?'): string
    {
        return implode(',', array_fill(0, $count, $placeholder));
    }

    /**
     * Run a SELECT and return one integer column.
     *
     * @param  array<int, mixed>  $bindings
     * @return list<int>
     */
    public static function ids(string $sql, array $bindings = [], string $column = 'id'): array
    {
        $ids = [];
        foreach (DB::select($sql, $bindings) as $row) {
            $ids[] = (int) $row->{$column};
        }

        return $ids;
    }

    /**
     * Whether the default connection can return the rows of an INSERT, which saves the id lookup
     * that otherwise follows a multi-row insert.
     */
    public static function supportsInsertReturning(): bool
    {
        $connection = DB::connection();
        $name = (string) $connection->getName();

        return self::$insertReturningSupport[$name] ??= $connection instanceof MySqlConnection
            && $connection->isMaria()
            && version_compare($connection->getServerVersion(), self::INSERT_RETURNING_MIN_MARIADB_VERSION, '>=');
    }

    /**
     * Run a data-changing statement that returns rows (INSERT ... RETURNING). Never uses a read
     * replica, unlike a plain select.
     *
     * @param  array<int, mixed>  $bindings
     * @return list<object>
     */
    public static function writeReturning(string $sql, array $bindings = []): array
    {
        return array_values(DB::select($sql, $bindings, false));
    }
}
