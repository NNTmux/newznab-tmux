<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop any index that covers binaries.binaryhash on its own.
 *
 * Numbered files hash to md5('file:N'), which is identical in every
 * collection, so a lone UNIQUE(binaryhash) lets file N exist in only one
 * collection database-wide. The legacy `binaries_binaryhash_unique` index
 * (from a since-deleted 2024 migration) survived the CBP migrations and
 * silently rolled back header chunks. Identity is UNIQUE(collections_id,
 * binaryhash) only.
 */
return new class extends Migration
{
    private const array TABLES = ['binaries', 'multigroup_binaries'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($this->singleColumnBinaryHashIndexes($table) as $indexName) {
                DB::statement(DB::getDriverName() === 'sqlite'
                    ? sprintf('DROP INDEX "%s"', $indexName)
                    : sprintf('DROP INDEX `%s` ON `%s`', $indexName, $table));
            }
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: restoring a global UNIQUE(binaryhash)
        // would reintroduce the cross-collection collision.
    }

    /**
     * @return list<string>
     */
    private function singleColumnBinaryHashIndexes(string $table): array
    {
        if (DB::getDriverName() === 'sqlite') {
            $names = [];
            foreach (DB::select("SELECT name FROM pragma_index_list('{$table}') WHERE origin = 'c'") as $index) {
                $columns = array_map(
                    static fn (object $column): string => (string) $column->name,
                    DB::select("SELECT name FROM pragma_index_info('{$index->name}')")
                );
                if ($columns === ['binaryhash']) {
                    $names[] = (string) $index->name;
                }
            }

            return $names;
        }

        $columnsByIndex = [];
        foreach (DB::select("SHOW INDEX FROM `{$table}`") as $row) {
            $columnsByIndex[(string) $row->Key_name][(int) $row->Seq_in_index] = (string) $row->Column_name;
        }

        $names = [];
        foreach ($columnsByIndex as $indexName => $columns) {
            ksort($columns);
            if ($indexName !== 'PRIMARY' && array_values($columns) === ['binaryhash']) {
                $names[] = $indexName;
            }
        }

        return $names;
    }
};
