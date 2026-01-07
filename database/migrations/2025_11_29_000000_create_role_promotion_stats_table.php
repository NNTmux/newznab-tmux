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
        Schema::create('role_promotion_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id');
            $table->unsignedBigInteger('role_promotion_id');
            $table->unsignedInteger('role_id');
            $table->integer('days_added')->comment('Number of days added to role expiry');
            $table->dateTime('previous_expiry_date')->nullable()->comment('Previous role expiry date before promotion');
            $table->dateTime('new_expiry_date')->nullable()->comment('New role expiry date after promotion');
            $table->timestamp('applied_at')->comment('When the promotion was applied');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_promotion_id')->references('id')->on('role_promotions')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');

            // Indexes for performance
            $table->index('user_id');
            $table->index('role_promotion_id');
            $table->index('role_id');
            $table->index('applied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_promotion_stats');
    }
};
