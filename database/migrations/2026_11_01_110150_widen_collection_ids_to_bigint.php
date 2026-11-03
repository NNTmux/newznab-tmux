<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen collections.id (and every column holding it) to BIGINT UNSIGNED.
 *
 * Production burned roughly 2.85M collection ids per hour against an INT
 * UNSIGNED ceiling of 4.29B. Ingest no longer wastes ids, but the headroom
 * removes the overflow risk for good. collections and binaries are small
 * transient tables; collection_groups must be pruned first
 * (`php artisan cbp:prune-orphans --rebuild-collection-groups`) so the copy
 * ALTER stays short.
 *
 * MariaDB DDL is not transactional, so every step is idempotent: a rerun
 * after a failure widens only the columns still on INT.
 */
return new class extends Migration
{
    private const int MAX_COLLECTION_GROUPS_ROWS = 2_000_000;

    /**
     * Table => [column, extra definition]. Only tables that exist are altered.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array COLUMNS = [
        'collections' => ['id', 'NOT NULL AUTO_INCREMENT'],
        'binaries' => ['collections_id', 'NOT NULL DEFAULT 0'],
        'collection_groups' => ['collections_id', 'NOT NULL'],
        'multigroup_collections' => ['id', 'NOT NULL AUTO_INCREMENT'],
        'multigroup_binaries' => ['collections_id', 'NOT NULL DEFAULT 0'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $pending = array_filter(
            self::COLUMNS,
            fn (array $spec, string $table): bool => Schema::hasTable($table) && $this->columnType($table, $spec[0]) !== 'bigint',
            ARRAY_FILTER_USE_BOTH
        );
        if ($pending === []) {
            return;
        }

        if (isset($pending['collection_groups'])) {
            $estimatedRows = (int) DB::scalar(
                'SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                ['collection_groups']
            );
            if ($estimatedRows > self::MAX_COLLECTION_GROUPS_ROWS) {
                throw new RuntimeException(
                    "collection_groups holds about {$estimatedRows} rows, most of them stale. Widening it would rewrite the whole table.\n".
                    'Run `php artisan tmux:stop`, then `php artisan cbp:prune-orphans --rebuild-collection-groups`, then rerun `php artisan migrate`.'
                );
            }
        }

        // InnoDB refuses to change a column used by a foreign key on either
        // side, whatever the constraint is called (e.g. FK_MGR_Collections).
        $foreignKeys = $this->foreignKeysTouching(array_keys($pending));
        foreach ($foreignKeys as $foreignKey) {
            DB::statement("ALTER TABLE `{$foreignKey['table']}` DROP FOREIGN KEY `{$foreignKey['name']}`");
        }

        foreach ($pending as $table => [$column, $definition]) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` BIGINT UNSIGNED {$definition}");
        }

        foreach ($foreignKeys as $foreignKey) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s` (%s) ON DELETE %s ON UPDATE %s',
                $foreignKey['table'],
                $foreignKey['name'],
                $this->columnList($foreignKey['columns']),
                $foreignKey['referenced_table'],
                $this->columnList($foreignKey['referenced_columns']),
                $foreignKey['delete_rule'],
                $foreignKey['update_rule'],
            ));
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: narrowing back to INT would fail once
        // ids pass 4,294,967,295 and reintroduces the overflow risk.
    }

    private function columnType(string $table, string $column): ?string
    {
        $type = DB::scalar(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $type === null ? null : strtolower((string) $type);
    }

    /**
     * Foreign keys whose child or parent column is one of the pending columns.
     *
     * Every child column of such a key must be widened too, otherwise the
     * key cannot be re-added; that is checked before any DDL runs.
     *
     * @param  list<string>  $tables
     * @return list<array{name: string, table: string, columns: list<string>, referenced_table: string, referenced_columns: list<string>, delete_rule: string, update_rule: string}>
     */
    private function foreignKeysTouching(array $tables): array
    {
        $rows = DB::select(
            'SELECT k.CONSTRAINT_NAME, k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                    r.DELETE_RULE, r.UPDATE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             JOIN information_schema.REFERENTIAL_CONSTRAINTS r
               ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME
             WHERE k.TABLE_SCHEMA = DATABASE() AND k.REFERENCED_TABLE_NAME IS NOT NULL
             ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION'
        );

        $isPending = static fn (string $table, string $column): bool => \in_array($table, $tables, true) && self::COLUMNS[$table][0] === $column;

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->TABLE_NAME.'.'.$row->CONSTRAINT_NAME;
            $grouped[$key] ??= [
                'name' => (string) $row->CONSTRAINT_NAME,
                'table' => (string) $row->TABLE_NAME,
                'columns' => [],
                'referenced_table' => (string) $row->REFERENCED_TABLE_NAME,
                'referenced_columns' => [],
                'delete_rule' => (string) $row->DELETE_RULE,
                'update_rule' => (string) $row->UPDATE_RULE,
                'touches' => false,
                'parent_pending' => false,
            ];
            $grouped[$key]['columns'][] = (string) $row->COLUMN_NAME;
            $grouped[$key]['referenced_columns'][] = (string) $row->REFERENCED_COLUMN_NAME;
            $childPending = $isPending((string) $row->TABLE_NAME, (string) $row->COLUMN_NAME);
            $parentPending = $isPending((string) $row->REFERENCED_TABLE_NAME, (string) $row->REFERENCED_COLUMN_NAME);
            $grouped[$key]['touches'] = $grouped[$key]['touches'] || $childPending || $parentPending;
            $grouped[$key]['parent_pending'] = $grouped[$key]['parent_pending'] || $parentPending;
        }

        $foreignKeys = [];
        foreach ($grouped as $foreignKey) {
            if (! $foreignKey['touches']) {
                continue;
            }
            if ($foreignKey['parent_pending']) {
                foreach ($foreignKey['columns'] as $column) {
                    $known = isset(self::COLUMNS[$foreignKey['table']]) && self::COLUMNS[$foreignKey['table']][0] === $column;
                    if (! $known) {
                        throw new RuntimeException(
                            "Foreign key {$foreignKey['name']} on {$foreignKey['table']}.{$column} references a column being widened to BIGINT. ".
                            'Add that column to this migration before rerunning it.'
                        );
                    }
                }
            }
            unset($foreignKey['touches'], $foreignKey['parent_pending']);
            $foreignKeys[] = $foreignKey;
        }

        return $foreignKeys;
    }

    /**
     * @param  list<string>  $columns
     */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
    }
};
