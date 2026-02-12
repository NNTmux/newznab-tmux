<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Covering index for the movie browse page release query.
     *
     * Optimizes the "top 2 releases per movie" UNION ALL subqueries which filter
     * by imdbid (equality), passwordstatus (range <=), categories_id (IN list),
     * and order by postdate DESC.
     *
     * With imdbid as the leading column, each subquery does a ref lookup per imdbid
     * value and reads rows in postdate DESC order from the index, filtering
     * passwordstatus and categories_id from the index key parts.
     */
    private string $indexName = 'ix_releases_imdbid_password_cat_postdate';

    private string $table = 'releases';

    public function up(): void
    {
        // Check if the index already exists before creating it
        $indexExists = DB::select(
            "SHOW INDEX FROM `{$this->table}` WHERE Key_name = ?",
            [$this->indexName]
        );

        if (empty($indexExists)) {
            DB::statement(
                "CREATE INDEX `{$this->indexName}` ON `{$this->table}` "
                .'(`imdbid`, `passwordstatus`, `categories_id`, `postdate` DESC)'
            );
        }
    }

    public function down(): void
    {
        $indexExists = DB::select(
            "SHOW INDEX FROM `{$this->table}` WHERE Key_name = ?",
            [$this->indexName]
        );

        if (! empty($indexExists)) {
            Schema::table($this->table, function ($table) {
                $table->dropIndex($this->indexName);
            });
        }
    }
};
