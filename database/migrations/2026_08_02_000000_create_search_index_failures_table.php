<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('search_index_failures')) {
            return;
        }

        Schema::create('search_index_failures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('release_id')->unique();
            $table->string('operation', 32)->default('upsert');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_index_failures');
    }
};
