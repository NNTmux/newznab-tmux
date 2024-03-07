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
        Schema::table('videos', function (Blueprint $table) {
            $table->string('imdb', 100)->default(0)->comment('ID number for IMDB site (without the \'tt\' prefix).')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->integer('imdb')->unsigned()->default(0)->index('ix_videos_imdb')->comment('ID number for IMDB site (without the \'tt\' prefix).');
        });
    }
};
