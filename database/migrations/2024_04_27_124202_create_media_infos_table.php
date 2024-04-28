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
        // Check if table exists
        if (! Schema::hasTable('media_infos')) {
            Schema::create('media_infos', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('releases_id')->unsigned();
                $table->string('movie_name')->nullable();
                $table->string('file_name')->nullable();
                $table->string('unique_id')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_infos');
    }
};
