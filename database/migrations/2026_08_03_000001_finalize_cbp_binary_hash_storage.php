<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite' || ! $this->hasCbpTables()) {
            return;
        }

        $issues = $this->schemaIssues();
        if ($issues === []) {
            return;
        }

        if (! config('nntmux.cbp.storage_migration_execute', false)) {
            throw new RuntimeException(
                "The CBP binary-hash storage migration requires an approved maintenance window.\n".
                'Stop ingestion and queue workers, verify a backup, run `php artisan cbp:optimize-storage`, '.
                'then set CBP_STORAGE_MIGRATION_EXECUTE=true and rerun `php artisan migrate`.'."\n".
                'Pending changes: '.implode('; ', $issues)
            );
        }

        $collectionCount = (int) DB::table('collections')->count();
        $exitCode = Artisan::call('cbp:optimize-storage', [
            '--execute' => true,
            '--batch' => (int) config('nntmux.cbp.reconcile_batch_size', 500),
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "The resumable CBP storage optimizer failed. Fix the reported error and rerun the migration.\n".
                Artisan::output()
            );
        }

        $optimizedCollectionCount = (int) DB::table('collections')->count();
        if ($optimizedCollectionCount !== $collectionCount) {
            throw new RuntimeException(
                "CBP storage optimization changed the collection row count ({$collectionCount} to {$optimizedCollectionCount}). Restore the backup before retrying."
            );
        }

        $issues = $this->schemaIssues();
        if ($issues !== []) {
            throw new RuntimeException(
                'CBP storage optimization finished without satisfying the migration contract: '.implode('; ', $issues)
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite' || ! $this->hasCbpTables()) {
            return;
        }

        throw new RuntimeException(
            'The CBP binary-hash migration cannot be rolled back: duplicate binaries and parts were merged. Restore the pre-migration backup instead.'
        );
    }

    private function hasCbpTables(): bool
    {
        return Schema::hasTable('collections')
            && Schema::hasTable('binaries')
            && Schema::hasTable('parts');
    }

    /** @return list<string> */
    private function schemaIssues(): array
    {
        $issues = [];

        if (! $this->binaryColumnIs('collections', 'collectionhash', 20)) {
            $issues[] = 'collections.collectionhash must be BINARY(20)';
        }
        if (! $this->binaryColumnIs('binaries', 'binaryhash', 16)) {
            $issues[] = 'binaries.binaryhash must be BINARY(16)';
        }
        if (! $this->indexMatches('binaries', 'ux_binaries_collection_hash', ['collections_id', 'binaryhash'], true)) {
            $issues[] = 'binaries requires UNIQUE (collections_id, binaryhash)';
        }
        if (! $this->indexMatches('binaries', 'ix_binaries_collection_filenumber', ['collections_id', 'filenumber'], false)) {
            $issues[] = 'binaries requires a non-unique (collections_id, filenumber) lookup index';
        }
        if ($this->hasUniqueIndexForColumns('binaries', ['collections_id', 'filenumber'])) {
            $issues[] = 'the legacy unique (collections_id, filenumber) constraint must be removed';
        }
        if (! $this->indexMatches('parts', 'PRIMARY', ['binaries_id', 'partnumber'], true)) {
            $issues[] = 'parts primary key must be (binaries_id, partnumber)';
        }
        if (! $this->indexMatches('parts', 'ix_parts_number', ['number'], false)) {
            $issues[] = 'parts requires a non-unique number lookup index';
        }
        if (! $this->messageIdUsesAsciiBinaryCollation()) {
            $issues[] = 'parts.messageid must use an ASCII binary collation';
        }

        return $issues;
    }

    private function binaryColumnIs(string $table, string $column, int $length): bool
    {
        $definition = DB::selectOne(
            'SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS max_length
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $definition !== null
            && strtolower((string) $definition->data_type) === 'binary'
            && (int) $definition->max_length === $length;
    }

    /** @param list<string> $columns */
    private function indexMatches(string $table, string $name, array $columns, bool $unique): bool
    {
        $rows = DB::select(
            'SELECT COLUMN_NAME AS column_name, NON_UNIQUE AS non_unique
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            [$table, $name]
        );

        if ($rows === []) {
            return false;
        }

        return array_map(static fn (object $row): string => (string) $row->column_name, $rows) === $columns
            && ((int) $rows[0]->non_unique === ($unique ? 0 : 1));
    }

    /** @param list<string> $columns */
    private function hasUniqueIndexForColumns(string $table, array $columns): bool
    {
        $rows = DB::select(
            'SELECT INDEX_NAME AS index_name, COLUMN_NAME AS column_name
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0
             ORDER BY INDEX_NAME, SEQ_IN_INDEX',
            [$table]
        );
        $indexes = [];
        foreach ($rows as $row) {
            $indexes[(string) $row->index_name][] = (string) $row->column_name;
        }

        return in_array($columns, $indexes, true);
    }

    private function messageIdUsesAsciiBinaryCollation(): bool
    {
        $definition = DB::selectOne(
            'SELECT CHARACTER_SET_NAME AS character_set_name, COLLATION_NAME AS collation_name
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['parts', 'messageid']
        );

        return $definition !== null
            && strtolower((string) $definition->character_set_name) === 'ascii'
            && str_ends_with(strtolower((string) $definition->collation_name), '_bin');
    }
};
