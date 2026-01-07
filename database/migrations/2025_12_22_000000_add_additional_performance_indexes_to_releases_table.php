<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional performance indexes for releases table.
     *
     * These complement the indexes added in:
     * - 2025_12_21_000000_add_categories_postdate_index_to_releases_table.php
     *   (ix_releases_password_categories_postdate: passwordstatus, categories_id, postdate DESC)
     * - 2025_12_21_000001_add_covering_index_for_tv_search.php
     *   (ix_releases_tv_search_covering: passwordstatus, categories_id, postdate DESC, videos_id, tv_episodes_id, groups_id)
     *
     * Index definitions: name => [columns, description]
     */
    private array $indexes = [
        'ix_releases_size_cat' => [
            'columns' => ['size', 'categories_id', 'passwordstatus'],
            'description' => 'Optimizes queries filtering by minSize parameter',
        ],
        'ix_releases_adddate' => [
            'columns' => ['adddate', 'categories_id'],
            'description' => 'Optimizes queries ordering by adddate',
        ],
        'ix_releases_grabs' => [
            'columns' => ['grabs', 'categories_id', 'postdate'],
            'description' => 'Optimizes trending/popular release queries',
        ],
        'ix_releases_movieinfo_cat' => [
            'columns' => ['movieinfo_id', 'categories_id', 'passwordstatus', 'postdate'],
            'description' => 'Optimizes movie search queries',
        ],
    ];

    public function up(): void
    {
        $table = 'releases';
        $existingIndexes = $this->getExistingIndexes($table);

        foreach ($this->indexes as $indexName => $indexDef) {
            $columns = $indexDef['columns'];

            // Check if an index with the same name exists
            if (isset($existingIndexes[$indexName])) {
                continue;
            }

            // Check if an index with the same columns already exists (under different name)
            if ($this->indexWithColumnsExists($existingIndexes, $columns)) {
                continue;
            }

            // Create the index
            $columnsList = implode(', ', array_map(fn ($col) => "`{$col}`", $columns));
            DB::statement("CREATE INDEX `{$indexName}` ON `{$table}` ({$columnsList})");
        }
    }

    public function down(): void
    {
        $table = 'releases';
        $existingIndexes = $this->getExistingIndexes($table);

        foreach ($this->indexes as $indexName => $indexDef) {
            if (isset($existingIndexes[$indexName])) {
                Schema::table($table, function ($table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    /**
     * Get all existing indexes for a table with their columns.
     *
     * @return array<string, array<string>> Index name => array of column names
     */
    private function getExistingIndexes(string $table): array
    {
        $indexes = [];
        $results = DB::select("SHOW INDEX FROM `{$table}`");

        foreach ($results as $row) {
            $indexName = $row->Key_name;
            $columnName = $row->Column_name;
            $seqInIndex = $row->Seq_in_index;

            if (! isset($indexes[$indexName])) {
                $indexes[$indexName] = [];
            }

            // Store columns in order by sequence
            $indexes[$indexName][$seqInIndex] = $columnName;
        }

        // Sort columns by sequence and convert to simple array
        foreach ($indexes as $name => $columns) {
            ksort($columns);
            $indexes[$name] = array_values($columns);
        }

        return $indexes;
    }

    /**
     * Check if an index with the exact same columns exists.
     *
     * @param  array<string, array<string>>  $existingIndexes
     * @param  array<string>  $columns
     */
    private function indexWithColumnsExists(array $existingIndexes, array $columns): bool
    {
        foreach ($existingIndexes as $indexColumns) {
            if ($indexColumns === $columns) {
                return true;
            }
        }

        return false;
    }
};
