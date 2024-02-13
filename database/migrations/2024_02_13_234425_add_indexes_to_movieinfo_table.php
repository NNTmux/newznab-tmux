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
        Schema::table('movieinfo', function (Blueprint $table) {
            $table->index('tmdbid', 'ix_movieinfo_tmdbid');
            $table->index('traktid', 'ix_movieinfo_traktid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movieinfo', function (Blueprint $table) {
            $table->dropIndex('ix_movieinfo_tmdbid');
            $table->dropIndex('ix_movieinfo_traktid');
        });
    }
};
