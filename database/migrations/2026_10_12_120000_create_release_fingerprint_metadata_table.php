<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_fingerprint_metadata', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id')->primary();
            $table->string('status', 20)->default('pending');
            $table->json('metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('collected_at')->nullable();
            $table->timestamps();
            $table->foreign('releases_id', 'FK_rfm_releases')
                ->references('id')->on('releases')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['status', 'releases_id'], 'release_fingerprint_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_fingerprint_metadata');
    }
};
