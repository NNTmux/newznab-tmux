<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This index specifically optimizes the TV/Movie API search queries that:
     * 1. Filter by passwordstatus (always <= 1 or = 0)
     * 2. Filter by categories_id IN (...) for TV categories
     * 3. Order by postdate DESC
     *
     * The index includes videos_id and tv_episodes_id as they are used in JOIN conditions,
     * allowing the optimizer to evaluate join necessity directly from the index.
     */
    public function up(): void
    {
        // Check if the index already exists
        $indexExists = DB::select("SHOW INDEX FROM releases WHERE Key_name = 'ix_releases_tv_search_covering'");

        if (empty($indexExists)) {
            // Covering index for TV search - includes FK columns to help optimizer
            DB::statement('CREATE INDEX ix_releases_tv_search_covering ON releases (passwordstatus, categories_id, postdate DESC, videos_id, tv_episodes_id, groups_id)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function ($table) {
            $table->dropIndex('ix_releases_tv_search_covering');
        });
    }
};

