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
        Schema::create('role_promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('applicable_roles')->comment('JSON array of role IDs this promotion applies to');
            $table->integer('additional_days')->default(0)->comment('Additional days added to role expiry');
            $table->date('start_date')->nullable()->comment('Promotion start date');
            $table->date('end_date')->nullable()->comment('Promotion end date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_promotions');
    }
};

