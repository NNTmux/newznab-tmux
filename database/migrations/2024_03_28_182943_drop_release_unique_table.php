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
        Schema::table('release_unique', function (Blueprint $table) {
            $table->drop();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('release_unique', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('releases_id')->unsigned();
            $table->string('uniqueid', 255)->nullable();
            $table->timestamps();
        });
    }
};
