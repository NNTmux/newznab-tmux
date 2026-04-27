<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a dedicated boolean column for "permanent" deletions on user_activities,
 * so the admin dashboard no longer needs whereJsonContains() (which can't use
 * a regular index) to count permanently-deleted users.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_activities')) {
            return;
        }

        if (! Schema::hasColumn('user_activities', 'is_permanent')) {
            Schema::table('user_activities', function (Blueprint $table): void {
                $table->boolean('is_permanent')->default(false)->after('metadata');
            });
        }

        if (! $this->indexExists('user_activities', 'ix_user_activities_type_permanent')) {
            Schema::table('user_activities', function (Blueprint $table): void {
                $table->index(['activity_type', 'is_permanent'], 'ix_user_activities_type_permanent');
            });
        }

        // Backfill existing rows where metadata->permanent is true.
        DB::table('user_activities')
            ->where('activity_type', 'deleted')
            ->whereRaw("JSON_EXTRACT(metadata, '$.permanent') = true")
            ->update(['is_permanent' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_activities')) {
            return;
        }

        if ($this->indexExists('user_activities', 'ix_user_activities_type_permanent')) {
            Schema::table('user_activities', function (Blueprint $table): void {
                $table->dropIndex('ix_user_activities_type_permanent');
            });
        }

        if (Schema::hasColumn('user_activities', 'is_permanent')) {
            Schema::table('user_activities', function (Blueprint $table): void {
                $table->dropColumn('is_permanent');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $rows = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

            return count($rows) > 0;
        } catch (Throwable) {
            return false;
        }
    }
};
