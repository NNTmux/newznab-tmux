<?php

declare(strict_types=1);

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
        Schema::create('service_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('status');
            $table->string('impact');
            $table->foreignId('service_status_id')->constrained('service_statuses')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            // Match legacy users.id type (typically INT UNSIGNED), not BIGINT
            $table->unsignedInteger('created_by')->nullable()->index();
            $table->timestamps();

            $table->index('status');
            $table->index('impact');
            $table->index('started_at');
            $table->index('resolved_at');
            $table->index(['status', 'started_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_incidents');
    }
};
