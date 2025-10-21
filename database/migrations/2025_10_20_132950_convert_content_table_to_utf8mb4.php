<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Convert the content table to utf8mb4 to support emojis
        DB::statement('ALTER TABLE content CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to utf8
        DB::statement('ALTER TABLE content CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci');
    }
};
