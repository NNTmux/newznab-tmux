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
        Schema::create('system_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 50)->index(); // 'cpu' or 'ram'
            $table->decimal('value', 8, 2); // The metric value (percentage or GB)
            $table->timestamp('recorded_at')->index(); // When the metric was recorded
            $table->timestamps();

            // Composite index for efficient querying
            $table->index(['metric_type', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_metrics');
    }
};
