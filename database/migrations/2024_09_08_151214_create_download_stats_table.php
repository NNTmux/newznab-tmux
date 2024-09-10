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
        Schema::create('download_stats', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('searchname')->nullable();
            $table->integer('grabs')->default(0);
            $table->string('guid')->nullable();
            $table->dateTime('adddate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_stats');
    }
};
