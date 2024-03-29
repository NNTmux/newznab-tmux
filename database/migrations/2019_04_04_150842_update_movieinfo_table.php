<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMovieinfoTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movieinfo', function (Blueprint $table) {
            $table->string('imdbid', 100)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movieinfo', function (Blueprint $table) {
            $table->unsignedMediumInteger('imdbid')->change();
        });
    }
}
