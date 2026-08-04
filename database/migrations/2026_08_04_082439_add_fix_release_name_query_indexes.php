<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private const INDEXES = [
        'par_hashes' => [
            'ix_par_hashes_hash_releases_id' => ['hash', 'releases_id'],
        ],
        'release_files' => [
            'ix_release_files_crc32_releases_id' => ['crc32', 'releases_id'],
        ],
        'predb' => [
            'ix_predb_searched_predate_id' => ['searched', 'predate', 'id'],
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->hasUsablePrefix($table, $columns)) {
                    continue;
                }

                Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (! Schema::hasIndex($table, $name)) {
                    continue;
                }

                Schema::table($table, static function (Blueprint $blueprint) use ($name): void {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }

    /**
     * @param  list<string>  $requiredColumns
     */
    private function hasUsablePrefix(string $table, array $requiredColumns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            $columns = array_values($index['columns'] ?? []);
            if (array_slice($columns, 0, count($requiredColumns)) === $requiredColumns) {
                return true;
            }
        }

        return false;
    }
};
