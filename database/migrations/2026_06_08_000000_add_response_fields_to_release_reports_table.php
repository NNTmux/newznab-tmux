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
        Schema::table('release_reports', function (Blueprint $table) {
            $table->text('response')->nullable()->after('description');
            $table->unsignedInteger('responded_by')->nullable()->after('reviewed_at');
            $table->timestamp('responded_at')->nullable()->after('responded_by');
            $table->boolean('response_is_public')->default(true)->after('responded_at');

            $table->foreign('responded_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['releases_id', 'response_is_public', 'responded_at'], 'release_reports_response_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('release_reports', function (Blueprint $table) {
            $table->dropIndex('release_reports_response_lookup_idx');
            $table->dropForeign(['responded_by']);
            $table->dropColumn(['response', 'responded_by', 'responded_at', 'response_is_public']);
        });
    }
};
