<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_verification_scratch', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('candidate_id')->nullable();
            $table->unsignedBigInteger('audit_release_id')->nullable();
            $table->boolean('terminal')->default(false);
            $table->string('audit_verdict', 32)->nullable();
            $table->text('audit_reason')->nullable();
            $table->string('delete_guid')->nullable();
            $table->text('delete_paths')->nullable();
            $table->index(['audit_release_id', 'terminal'], 'verification_scratch_audit_terminal');
            $table->unsignedInteger('version')->default(0);
            $table->unique(['candidate_id', 'version'], 'verification_scratch_candidate_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_verification_scratch');
    }
};
