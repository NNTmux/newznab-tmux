<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a composite index on (videos_id, categories_id) for the releases table
     * to optimize the EXISTS subquery used in Video::getSeriesList().
     */
    public function up(): void
    {
        // Check if the index already exists before creating it
        $indexExists = DB::select("
            SHOW INDEX FROM releases WHERE Key_name = 'ix_releases_videos_categories'
        ");

        if (empty($indexExists)) {
            Schema::table('releases', function (Blueprint $table) {
                $table->index(['videos_id', 'categories_id'], 'ix_releases_videos_categories');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('ix_releases_videos_categories');
        });
    }
};
