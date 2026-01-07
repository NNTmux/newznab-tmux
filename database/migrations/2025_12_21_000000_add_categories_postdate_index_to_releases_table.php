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
     * This index optimizes queries that filter by passwordstatus + categories_id and order by postdate DESC,
     * which is the common pattern for TV/Movie search APIs.
     *
     * Column order rationale:
     * - passwordstatus first: low cardinality but always filtered with <= comparison
     * - categories_id second: filtered with IN() clause
     * - postdate third (DESC): used for ORDER BY, avoids filesort
     */
    public function up(): void
    {
        // Use raw SQL to specify DESC for postdate which Laravel Blueprint doesn't support
        DB::statement('CREATE INDEX ix_releases_password_categories_postdate ON releases (passwordstatus, categories_id, postdate DESC)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex('ix_releases_password_categories_postdate');
        });
    }
};
