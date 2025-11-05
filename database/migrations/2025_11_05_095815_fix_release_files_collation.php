<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix collation for release_files table to use utf8mb4_unicode_ci
        DB::statement('ALTER TABLE release_files CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        // Explicitly update the varchar columns to ensure they use utf8mb4_unicode_ci
        DB::statement('ALTER TABLE release_files MODIFY COLUMN name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE release_files MODIFY COLUMN crc32 VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\'');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to utf8mb3_unicode_ci (only if you really need to rollback)
        DB::statement('ALTER TABLE release_files CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
        DB::statement('ALTER TABLE release_files MODIFY COLUMN name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL');
        DB::statement('ALTER TABLE release_files MODIFY COLUMN crc32 VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT \'\'');
    }
};
