<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IP addresses refused by the DenyBlockedIps middleware. A row is kept after its block
     * ends (expires_at in the past) so a repeat offence can be blocked for longer.
     */
    public function up(): void
    {
        if (Schema::hasTable('blocked_ips')) {
            return;
        }

        Schema::create('blocked_ips', function (Blueprint $table): void {
            $table->id();
            $table->string('ip_address', 45)->unique();
            $table->string('source', 16);
            $table->string('reason')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->unsignedSmallInteger('block_count')->default(1);
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};
