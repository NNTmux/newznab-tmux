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
        Schema::create('log_index_files', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->unsignedBigInteger('device');
            $table->unsignedBigInteger('inode');
            $table->char('head_hash', 40);
            $table->unsignedInteger('head_length');
            $table->boolean('structured')->default(true);
            $table->unsignedBigInteger('indexed_offset')->default(0);
            $table->unsignedBigInteger('indexed_line')->default(0);
            $table->unsignedBigInteger('indexed_size')->default(0);
            $table->dateTime('indexed_at')->nullable();
            $table->timestamps();

            $table->unique(['device', 'inode']);
            $table->index('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_index_files');
    }
};
