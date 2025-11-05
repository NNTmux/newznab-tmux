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
        // Get all tables with utf8mb3 collation and convert them to utf8mb4
        $database = config('database.connections.mariadb.database');

        $tables = DB::select("
            SELECT DISTINCT TABLE_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = ?
            AND COLLATION_NAME LIKE 'utf8mb3%'
            ORDER BY TABLE_NAME
        ", [$database]);

        foreach ($tables as $table) {
            $tableName = $table->TABLE_NAME;
            echo "Converting table: {$tableName}\n";

            try {
                // Convert table to utf8mb4
                DB::statement("ALTER TABLE `{$tableName}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (\Exception $e) {
                echo "Warning: Could not convert table {$tableName}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is intentionally left empty as reverting to utf8mb3 is generally not recommended
        // and could cause data loss for characters that require utf8mb4
    }
};
