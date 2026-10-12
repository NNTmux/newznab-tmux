<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_verification_candidates', function (Blueprint $table): void {
            $table->id();
            $table->char('identity', 40)->unique();
            $table->char('source_identity', 40)->nullable()->index();
            $table->unsignedInteger('groups_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('manifest_path');
            $table->unsignedBigInteger('manifest_bytes');
            $table->char('manifest_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->dateTime('last_unique_at');
            $table->dateTime('next_attempt_at');
            $table->string('lease_token', 64)->nullable();
            $table->dateTime('lease_until')->nullable();
            $table->unsignedBigInteger('release_id')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->index(['status', 'next_attempt_at', 'id'], 'verification_due');
        });
        Schema::create('release_verification_state', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('pending_bytes')->default(0);
            $table->unsignedBigInteger('audit_cursor')->default(0);
        });
        DB::table('release_verification_state')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('release_verification_candidates');
        Schema::dropIfExists('release_verification_state');
    }
};
