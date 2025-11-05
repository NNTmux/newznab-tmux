<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the foreign key constraint
        DB::statement('ALTER TABLE telescope_entries_tags DROP FOREIGN KEY telescope_entries_tags_entry_uuid_foreign');

        // Convert both tables to utf8mb4
        DB::statement('ALTER TABLE telescope_entries CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        DB::statement('ALTER TABLE telescope_entries_tags CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        // Re-add the foreign key constraint
        DB::statement('ALTER TABLE telescope_entries_tags ADD CONSTRAINT telescope_entries_tags_entry_uuid_foreign FOREIGN KEY (entry_uuid) REFERENCES telescope_entries(uuid) ON DELETE CASCADE');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is intentionally left empty as reverting is not recommended
    }
};
