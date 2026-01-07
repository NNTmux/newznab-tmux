<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if table doesn't exist (e.g., fresh SQLite test database)
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        // Skip if column already exists
        if (Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('uuid')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('failed_jobs')) {
            return;
        }

        if (! Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
