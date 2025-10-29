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
        Schema::create('user_activity_stats_hourly', function (Blueprint $table) {
            $table->id();
            $table->dateTime('stat_hour')->index();
            $table->integer('downloads_count')->default(0);
            $table->integer('api_hits_count')->default(0);
            $table->timestamps();

            // Ensure one record per hour
            $table->unique('stat_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_stats_hourly');
    }
};
