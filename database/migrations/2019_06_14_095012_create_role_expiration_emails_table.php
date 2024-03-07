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
        Schema::create('role_expiration_emails', function (Blueprint $table) {
            $table->id();
            $table->integer('users_id')->unique();
            $table->timestamps();
            $table->boolean('day')->default(false);
            $table->boolean('week')->default(false);
            $table->boolean('month')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_expiration_emails');
    }
};
