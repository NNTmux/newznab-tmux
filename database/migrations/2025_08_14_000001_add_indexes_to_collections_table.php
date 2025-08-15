<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            // Create composite index for (groups_id, added) used by stuck-collection cleanup.
            $table->index(['groups_id', 'added'], 'collections_groups_added_idx');
            // Create index for dateadded used by old-collection cleanup.
            $table->index(['dateadded'], 'collections_dateadded_idx');
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->dropIndex('collections_groups_added_idx');
            $table->dropIndex('collections_dateadded_idx');
        });
    }
};
