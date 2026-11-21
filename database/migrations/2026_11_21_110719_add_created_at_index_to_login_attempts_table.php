<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index for the admin login attempts list (date filters, last-day summary) and for pruning.
     */
    private const string INDEX = 'ix_login_attempts_created_at';

    public function up(): void
    {
        if (! Schema::hasTable('login_attempts') || Schema::hasIndex('login_attempts', self::INDEX)) {
            return;
        }

        Schema::table('login_attempts', function (Blueprint $table): void {
            $table->index('created_at', self::INDEX);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('login_attempts') || ! Schema::hasIndex('login_attempts', self::INDEX)) {
            return;
        }

        Schema::table('login_attempts', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
