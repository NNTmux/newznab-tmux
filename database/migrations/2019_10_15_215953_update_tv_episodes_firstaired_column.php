<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTvEpisodesFirstairedColumn extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tv_episodes', function (Blueprint $table) {
            $table->date('firstaired')->nullable()->comment('Date of original airing/release.')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tv_episodes', function (Blueprint $table) {
            $table->date('firstaired')->comment('Date of original airing/release.')->change();
        });
    }
}
