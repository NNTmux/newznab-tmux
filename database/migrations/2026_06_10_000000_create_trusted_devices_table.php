<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clean up any partially-created table from a previously failed run
        // (the table can be created before the foreign-key ALTER statement runs).
        Schema::dropIfExists('trusted_devices');

        Schema::create('trusted_devices', function (Blueprint $table): void {
            $table->id();
            // users.id is INT UNSIGNED in this schema, so the FK column must match
            // its type or MariaDB raises errno 150 "incorrectly formed" constraint.
            $table->unsignedInteger('user_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_used_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_devices');
    }
};
