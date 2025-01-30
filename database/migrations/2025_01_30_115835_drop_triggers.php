<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop triggers from releases, release_files and predb_hashes tables by using raw mysql queries
        DB::unprepared('DROP TRIGGER IF EXISTS `trigger_predb_insert_hashes_after_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trigger_predb_update_hashes_after_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trigger_predb_delete_hashes_after_delete`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trigger_release_files_check_rfinsert_before_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trigger_release_files_check_rfupdate_before_update`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trigger_releases_check_insert_before_insert`');
        DB::unprepared('DROP TRIGGER IF EXISTS `trigger_releases_check_update_before_update`');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
