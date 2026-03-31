<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing indexes across multiple tables to improve query performance.
 *
 * Targets: FK columns used in JOINs, columns in frequent WHERE/ORDER BY clauses,
 * and composite indexes for hot query paths that lack index coverage.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Priority 1: High-traffic FK columns missing indexes

        if (! $this->indexExists('user_downloads', 'ix_user_downloads_releases_id')) {
            Schema::table('user_downloads', function (Blueprint $table) {
                $table->index('releases_id', 'ix_user_downloads_releases_id');
            });
        }

        if (Schema::hasTable('media_infos') && ! $this->indexExists('media_infos', 'ix_media_infos_releases_id')) {
            Schema::table('media_infos', function (Blueprint $table) {
                $table->index('releases_id', 'ix_media_infos_releases_id');
            });
        }

        if (! $this->indexExists('password_securities', 'ix_password_securities_user_id')) {
            Schema::table('password_securities', function (Blueprint $table) {
                $table->index('user_id', 'ix_password_securities_user_id');
            });
        }

        if (! $this->indexExists('paypal_payments', 'ix_paypal_payments_users_id')) {
            Schema::table('paypal_payments', function (Blueprint $table) {
                $table->index('users_id', 'ix_paypal_payments_users_id');
            });
        }

        // Priority 2: Genre FK on info tables used in browse/search JOINs

        if (! $this->indexExists('musicinfo', 'ix_musicinfo_genres_id')) {
            Schema::table('musicinfo', function (Blueprint $table) {
                $table->index('genres_id', 'ix_musicinfo_genres_id');
            });
        }

        if (! $this->indexExists('consoleinfo', 'ix_consoleinfo_genres_id')) {
            Schema::table('consoleinfo', function (Blueprint $table) {
                $table->index('genres_id', 'ix_consoleinfo_genres_id');
            });
        }

        if (! $this->indexExists('gamesinfo', 'ix_gamesinfo_genres_id')) {
            Schema::table('gamesinfo', function (Blueprint $table) {
                $table->index('genres_id', 'ix_gamesinfo_genres_id');
            });
        }

        // Priority 3: Forum tables — read-tracking has zero indexes

        if (! $this->indexExists('forum_threads_read', 'ix_forum_threads_read_user_thread')) {
            Schema::table('forum_threads_read', function (Blueprint $table) {
                $table->index(['user_id', 'thread_id'], 'ix_forum_threads_read_user_thread');
            });
        }

        if (! $this->indexExists('forum_threads', 'ix_forum_threads_author_id')) {
            Schema::table('forum_threads', function (Blueprint $table) {
                $table->index('author_id', 'ix_forum_threads_author_id');
            });
        }

        // Priority 4: User/role/admin tables

        if (! $this->indexExists('users', 'ix_users_roles_created')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['roles_id', 'created_at'], 'ix_users_roles_created');
            });
        }

        if (Schema::hasColumn('users', 'deleted_at') && ! $this->indexExists('users', 'ix_users_deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('deleted_at', 'ix_users_deleted_at');
            });
        }

        if (Schema::hasTable('user_role_history') && ! $this->indexExists('user_role_history', 'ix_user_role_history_changed_by')) {
            Schema::table('user_role_history', function (Blueprint $table) {
                $table->index('changed_by', 'ix_user_role_history_changed_by');
            });
        }

        if (Schema::hasTable('user_role_history') && ! $this->indexExists('user_role_history', 'ix_user_role_history_roles')) {
            Schema::table('user_role_history', function (Blueprint $table) {
                $table->index(['old_role_id', 'new_role_id'], 'ix_user_role_history_roles');
            });
        }

        if (Schema::hasTable('role_promotions') && ! $this->indexExists('role_promotions', 'ix_role_promotions_is_active')) {
            Schema::table('role_promotions', function (Blueprint $table) {
                $table->index('is_active', 'ix_role_promotions_is_active');
            });
        }

        // Priority 5: Stats tables — time-range reporting

        if (Schema::hasTable('grab_stats') && ! $this->indexExists('grab_stats', 'ix_grab_stats_created_at')) {
            Schema::table('grab_stats', function (Blueprint $table) {
                $table->index('created_at', 'ix_grab_stats_created_at');
            });
        }

        if (Schema::hasTable('download_stats') && ! $this->indexExists('download_stats', 'ix_download_stats_created_at')) {
            Schema::table('download_stats', function (Blueprint $table) {
                $table->index('created_at', 'ix_download_stats_created_at');
            });
        }

        if (Schema::hasTable('role_stats') && ! $this->indexExists('role_stats', 'ix_role_stats_created_at')) {
            Schema::table('role_stats', function (Blueprint $table) {
                $table->index('created_at', 'ix_role_stats_created_at');
            });
        }

        // Priority 6: Composite / covering indexes for hot query paths

        if (! $this->indexExists('genres', 'ix_genres_type_disabled')) {
            Schema::table('genres', function (Blueprint $table) {
                $table->index(['type', 'disabled'], 'ix_genres_type_disabled');
            });
        }

        if (! $this->indexExists('content', 'ix_content_type_ordinal')) {
            Schema::table('content', function (Blueprint $table) {
                $table->index(['contenttype', 'ordinal'], 'ix_content_type_ordinal');
            });
        }

        if (! $this->indexExists('invitations', 'ix_invitations_active_expires')) {
            Schema::table('invitations', function (Blueprint $table) {
                $table->index(['is_active', 'expires_at'], 'ix_invitations_active_expires');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'user_downloads' => 'ix_user_downloads_releases_id',
            'media_infos' => 'ix_media_infos_releases_id',
            'password_securities' => 'ix_password_securities_user_id',
            'paypal_payments' => 'ix_paypal_payments_users_id',
            'musicinfo' => 'ix_musicinfo_genres_id',
            'consoleinfo' => 'ix_consoleinfo_genres_id',
            'gamesinfo' => 'ix_gamesinfo_genres_id',
            'forum_threads_read' => 'ix_forum_threads_read_user_thread',
            'forum_threads' => 'ix_forum_threads_author_id',
            'users' => ['ix_users_roles_created', 'ix_users_deleted_at'],
            'user_role_history' => ['ix_user_role_history_changed_by', 'ix_user_role_history_roles'],
            'role_promotions' => 'ix_role_promotions_is_active',
            'grab_stats' => 'ix_grab_stats_created_at',
            'download_stats' => 'ix_download_stats_created_at',
            'role_stats' => 'ix_role_stats_created_at',
            'genres' => 'ix_genres_type_disabled',
            'content' => 'ix_content_type_ordinal',
            'invitations' => 'ix_invitations_active_expires',
        ];

        foreach ($indexes as $table => $indexNames) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $indexNames = (array) $indexNames;
            foreach ($indexNames as $indexName) {
                if ($this->indexExists($table, $indexName)) {
                    Schema::table($table, function (Blueprint $table) use ($indexName) {
                        $table->dropIndex($indexName);
                    });
                }
            }
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }
};
