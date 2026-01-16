<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove the legacy users_id column since we now use invited_by
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Drop the old users_id column if it exists and invited_by exists
            if (Schema::hasColumn('invitations', 'users_id') && Schema::hasColumn('invitations', 'invited_by')) {
                $table->dropColumn('users_id');
            }

            // Drop guid column if it still exists (we use token now)
            if (Schema::hasColumn('invitations', 'guid')) {
                $table->dropColumn('guid');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            if (! Schema::hasColumn('invitations', 'users_id')) {
                $table->unsignedInteger('users_id')->nullable()->after('id');
            }
        });
    }
};
