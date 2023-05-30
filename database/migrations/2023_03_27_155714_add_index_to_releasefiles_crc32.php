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
        Schema::table('release_files', function (Blueprint $table) {
            $table->index(['crc32'], 'ix_releasefiles_crc32');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('release_files', function (Blueprint $table) {
            $table->dropIndex('ix_releasefiles_crc32');
        });
    }
};
