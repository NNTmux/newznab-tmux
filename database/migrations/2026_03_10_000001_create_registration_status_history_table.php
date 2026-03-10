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
        Schema::create('registration_status_history', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100)->index();
            $table->unsignedTinyInteger('old_status')->nullable();
            $table->unsignedTinyInteger('new_status')->nullable();
            $table->unsignedBigInteger('registration_period_id')->nullable()->index();
            $table->unsignedBigInteger('changed_by')->nullable()->index();
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registration_status_history');
    }
};
