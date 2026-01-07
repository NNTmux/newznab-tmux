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
        Schema::create('user_role_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->integer('old_role_id')->nullable();
            $table->integer('new_role_id');
            $table->datetime('old_expiry_date')->nullable()->comment('Previous role expiry date');
            $table->datetime('new_expiry_date')->nullable()->comment('New role expiry date');
            $table->datetime('effective_date')->comment('When this role change became active');
            $table->boolean('is_stacked')->default(false)->comment('Was this role stacked after previous expiry');
            $table->string('change_reason')->nullable()->comment('Reason for role change (upgrade, downgrade, expiry, admin, etc)');
            $table->unsignedBigInteger('changed_by')->nullable()->comment('Admin user ID who made the change');
            $table->timestamps();

            // Note: Foreign key constraint removed due to potential schema conflicts
            // Ensure referential integrity at application level
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role_history');
    }
};
