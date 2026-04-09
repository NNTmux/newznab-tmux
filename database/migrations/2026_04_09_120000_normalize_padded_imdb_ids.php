<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize legacy zero-padded IMDb IDs while preserving legitimate leading-zero IDs.
     *
     * Historically some paths stored IMDb IDs with imdb_id_pad(), which converted 7-digit
     * IDs like 0137523 into 8-digit padded values. Real 8+ digit IMDb IDs are never expected
     * to start with 0, so values matching /^0\d{7}$/ can safely drop a single leading zero.
     *
     * Sentinel values like 0000000 / 00000000 become empty strings to preserve the existing
     * "attempted but unresolved" behavior in string-based code paths.
     */
    public function up(): void
    {
        $this->normalizeImdbColumn('releases', 'imdbid');
        $this->normalizeImdbColumn('movieinfo', 'imdbid');
        $this->normalizeImdbColumn('user_movies', 'imdbid');
        $this->normalizeImdbColumn('videos', 'imdb');
    }

    public function down(): void
    {
        // No-op: this migration removes lossy zero-padding and cannot be safely reversed.
    }

    private function normalizeImdbColumn(string $table, string $column): void
    {
        DB::table($table)
            ->whereIn($column, ['0000000', '00000000', '0'])
            ->update([$column => '']);

        DB::table($table)
            ->whereRaw(sprintf("`%s` REGEXP '^0[0-9]{7}$'", $column))
            ->update([$column => DB::raw(sprintf('SUBSTRING(`%s`, 2)', $column))]);
    }
};
