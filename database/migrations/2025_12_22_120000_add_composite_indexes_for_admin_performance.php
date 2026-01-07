<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add composite indexes to improve admin page query performance.
 * These indexes optimize queries used on admin dashboard and user list pages.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite index for user_requests - optimizes counting API requests per user per day
        if (! $this->indexExists('user_requests', 'ix_user_requests_users_timestamp')) {
            Schema::table('user_requests', function (Blueprint $table) {
                $table->index(['users_id', 'timestamp'], 'ix_user_requests_users_timestamp');
            });
        }

        // Add composite index for user_downloads - optimizes counting downloads per user per day
        if (! $this->indexExists('user_downloads', 'ix_user_downloads_users_timestamp')) {
            Schema::table('user_downloads', function (Blueprint $table) {
                $table->index(['users_id', 'timestamp'], 'ix_user_downloads_users_timestamp');
            });
        }

        // Add index on releases.adddate for faster "today's releases" count
        // Only add if not already exists from previous migration
        if (! $this->indexExists('releases', 'ix_releases_adddate_only')) {
            Schema::table('releases', function (Blueprint $table) {
                $table->index(['adddate'], 'ix_releases_adddate_only');
            });
        }

        // Add index on users.created_at for faster "today's users" count
        if (! $this->indexExists('users', 'ix_users_created_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['created_at'], 'ix_users_created_at');
            });
        }

        // Add index on system_metrics for faster historical data retrieval
        if (Schema::hasTable('system_metrics') && ! $this->indexExists('system_metrics', 'ix_system_metrics_type_recorded')) {
            Schema::table('system_metrics', function (Blueprint $table) {
                $table->index(['metric_type', 'recorded_at'], 'ix_system_metrics_type_recorded');
            });
        }

        // Add index on user_activity_stats_hourly for faster hourly data retrieval
        if (Schema::hasTable('user_activity_stats_hourly') && ! $this->indexExists('user_activity_stats_hourly', 'ix_hourly_stats_hour')) {
            Schema::table('user_activity_stats_hourly', function (Blueprint $table) {
                $table->index(['stat_hour'], 'ix_hourly_stats_hour');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_requests', function (Blueprint $table) {
            $table->dropIndex('ix_user_requests_users_timestamp');
        });

        Schema::table('user_downloads', function (Blueprint $table) {
            $table->dropIndex('ix_user_downloads_users_timestamp');
        });

        if ($this->indexExists('releases', 'ix_releases_adddate_only')) {
            Schema::table('releases', function (Blueprint $table) {
                $table->dropIndex('ix_releases_adddate_only');
            });
        }

        if ($this->indexExists('users', 'ix_users_created_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('ix_users_created_at');
            });
        }

        if (Schema::hasTable('system_metrics') && $this->indexExists('system_metrics', 'ix_system_metrics_type_recorded')) {
            Schema::table('system_metrics', function (Blueprint $table) {
                $table->dropIndex('ix_system_metrics_type_recorded');
            });
        }

        if (Schema::hasTable('user_activity_stats_hourly') && $this->indexExists('user_activity_stats_hourly', 'ix_hourly_stats_hour')) {
            Schema::table('user_activity_stats_hourly', function (Blueprint $table) {
                $table->dropIndex('ix_hourly_stats_hour');
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = \DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }
};
