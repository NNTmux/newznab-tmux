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
        Schema::create('par2_rename_progress', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id')->primary();
            $table->string('signature', 64);
            $table->unsignedInteger('offset');
            $table->json('retry_indexes');
            $table->timestamp('updated_at');
            $table->foreign('releases_id')->references('id')->on('releases')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('par2_rename_progress');
    }
};
