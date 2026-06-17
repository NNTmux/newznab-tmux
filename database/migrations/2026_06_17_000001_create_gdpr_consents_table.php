<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gdpr_consents')) {
            return;
        }

        Schema::create('gdpr_consents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('consent_type', 64);
            $table->string('status', 32)->default('granted');
            $table->string('policy_version', 64)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'consent_type'], 'ix_gdpr_consents_user_type');
            $table->index(['consent_type', 'status'], 'ix_gdpr_consents_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_consents');
    }
};
