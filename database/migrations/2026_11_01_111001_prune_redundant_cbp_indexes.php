<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trim write amplification on the CBP ingest tables.
 *
 * collections and binaries take a write for every header chunk, and every
 * secondary index adds to it. The dropped indexes are exact duplicates,
 * left prefixes of another index, or serve no query. Names differ between
 * long-lived installs and the schema dump, so both spellings are listed.
 * Retention by last activity gets its own index.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const array DROP = [
        // ix_collection_dateadded duplicates collections_dateadded_idx;
        // filecheck / groups_id are prefixes of composite indexes;
        // fromname, date and added are never filtered on.
        'collections' => ['ix_collection_dateadded', 'ix_collection_filecheck', 'group_id', 'groups_id', 'fromname', 'date', 'ix_collection_added'],
        // collections_id is a prefix of ux_binaries_collection_hash; nothing filters on partcheck.
        'binaries' => ['ix_binary_collection', 'ix_binaries_collection', 'ix_binary_partcheck', 'ix_binaries_partcheck'],
        // Covered by UNIQUE (numberid, groups_id) plus (groups_id, attempts).
        'missed_parts' => ['ix_partrepair_numberid_groupid_attempts', 'ix_missed_parts_numberid_groupsid_attempts'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach (self::DROP as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $index) {
                if ($this->indexExists($table, $index) && ! $this->backsForeignKey($table, $index)) {
                    DB::statement("ALTER TABLE `{$table}` DROP INDEX `{$index}`");
                }
            }
        }

        if (Schema::hasTable('collections') && Schema::hasColumn('collections', 'last_seen_at')
            && ! $this->indexExists('collections', 'ix_collections_last_seen_at')) {
            DB::statement('CREATE INDEX `ix_collections_last_seen_at` ON `collections` (`last_seen_at`)');
        }

        // Both tables are small and rewritten constantly; page compression
        // only costs CPU on every write.
        foreach (['collections', 'binaries'] as $table) {
            if (Schema::hasTable($table) && $this->rowFormat($table) === 'compressed') {
                DB::statement("ALTER TABLE `{$table}` ROW_FORMAT=DYNAMIC");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (Schema::hasTable('collections') && $this->indexExists('collections', 'ix_collections_last_seen_at')) {
            DB::statement('ALTER TABLE `collections` DROP INDEX `ix_collections_last_seen_at`');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== [];
    }

    /**
     * InnoDB refuses to drop the only index that supports a foreign key.
     */
    private function backsForeignKey(string $table, string $index): bool
    {
        $columns = array_map(
            static fn (object $row): string => (string) $row->Column_name,
            DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])
        );
        if ($columns === []) {
            return false;
        }

        $foreignKeyColumns = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$table]
        );
        foreach ($foreignKeyColumns as $row) {
            if ($columns[0] !== (string) $row->COLUMN_NAME) {
                continue;
            }

            // Safe when another index also starts with the foreign key column.
            $alternatives = DB::select(
                "SHOW INDEX FROM `{$table}` WHERE Seq_in_index = 1 AND Column_name = ? AND Key_name <> ?",
                [$columns[0], $index]
            );

            return $alternatives === [];
        }

        return false;
    }

    private function rowFormat(string $table): ?string
    {
        $format = DB::scalar(
            'SELECT ROW_FORMAT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return $format === null ? null : strtolower((string) $format);
    }
};
