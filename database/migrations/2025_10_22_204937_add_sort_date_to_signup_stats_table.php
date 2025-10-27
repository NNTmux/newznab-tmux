<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('signup_stats', function (Blueprint $table) {
            $table->date('sort_date')->nullable()->after('month');
            $table->index('sort_date');
        });

        // Populate sort_date for existing records
        // Parse month strings like "October 2025" to create sortable dates
        DB::statement("
            UPDATE signup_stats
            SET sort_date = STR_TO_DATE(CONCAT('01 ', month), '%d %M %Y')
            WHERE sort_date IS NULL AND month IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('signup_stats', function (Blueprint $table) {
            $table->dropIndex(['sort_date']);
            $table->dropColumn('sort_date');
        });
    }
};
