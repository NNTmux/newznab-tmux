<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gdpr_audit_logs')) {
            return;
        }

        Schema::create('gdpr_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('gdpr_request_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('event', 64);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['gdpr_request_id', 'created_at'], 'ix_gdpr_audit_request_created');
            $table->index(['user_id', 'created_at'], 'ix_gdpr_audit_user_created');
            $table->index(['event', 'created_at'], 'ix_gdpr_audit_event_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_audit_logs');
    }
};
