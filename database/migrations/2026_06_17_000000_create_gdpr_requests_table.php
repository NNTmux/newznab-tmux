<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gdpr_requests')) {
            return;
        }

        Schema::create('gdpr_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('requester_username')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('type', 32);
            $table->string('status', 32)->default('pending');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('export_disk')->nullable();
            $table->string('export_path')->nullable();
            $table->timestamp('export_expires_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type', 'status'], 'ix_gdpr_requests_user_type_status');
            $table->index(['status', 'created_at'], 'ix_gdpr_requests_status_created');
            $table->index(['type', 'created_at'], 'ix_gdpr_requests_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gdpr_requests');
    }
};
