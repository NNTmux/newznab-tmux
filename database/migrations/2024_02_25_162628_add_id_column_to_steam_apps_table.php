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
        Schema::table('steam_apps', function (Blueprint $table) {
            $table->dropPrimary(['appid', 'name']);
        });

        Schema::table('steam_apps', function (Blueprint $table) {
            $table->id();
            $table->index(['name', 'appid'], 'ix_name_appid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('steam_apps', function (Blueprint $table) {
            $table->dropColumn('id');
            $table->dropIndex('ix_name_appid');
            $table->primary(['appid', 'name']);
        });
    }
};
