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
        Schema::create('release_verification_audit_retries', function (Blueprint $table): void {
            $table->unsignedBigInteger('release_id')->primary();
            $table->dateTime('next_attempt_at');
            $table->text('reason');
            $table->index(['next_attempt_at', 'release_id'], 'verification_audit_due');
        });
        Schema::table('release_verification_state', function (Blueprint $table): void {
            $table->boolean('audit_retry_turn')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('release_verification_state', function (Blueprint $table): void {
            $table->dropColumn('audit_retry_turn');
        });
        Schema::dropIfExists('release_verification_audit_retries');
    }
};
