<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('invitations')) {
            // Table exists, let's modify it to add missing columns for our custom system
            Schema::table('invitations', function (Blueprint $table) {
                // Check and add columns that might be missing
                if (!Schema::hasColumn('invitations', 'token')) {
                    $table->string('token', 64)->unique()->after('id');
                }

                if (!Schema::hasColumn('invitations', 'email')) {
                    $table->string('email')->after('token');
                }

                if (!Schema::hasColumn('invitations', 'invited_by')) {
                    $table->unsignedBigInteger('invited_by')->after('email');
                    $table->index('invited_by');
                }

                if (!Schema::hasColumn('invitations', 'expires_at')) {
                    $table->timestamp('expires_at')->after('invited_by');
                }

                if (!Schema::hasColumn('invitations', 'used_at')) {
                    $table->timestamp('used_at')->nullable()->after('expires_at');
                }

                if (!Schema::hasColumn('invitations', 'used_by')) {
                    $table->unsignedBigInteger('used_by')->nullable()->after('used_at');
                    $table->index('used_by');
                }

                if (!Schema::hasColumn('invitations', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('used_by');
                }

                if (!Schema::hasColumn('invitations', 'metadata')) {
                    $table->json('metadata')->nullable()->after('is_active');
                }

                // Add timestamps if they don't exist
                if (!Schema::hasColumn('invitations', 'created_at')) {
                    $table->timestamps();
                }
            });

            // Add composite indexes safely
            $this->addIndexSafely('invitations', ['token', 'is_active'], 'invitations_token_is_active_index');
            $this->addIndexSafely('invitations', ['email', 'is_active'], 'invitations_email_is_active_index');
            $this->addIndexSafely('invitations', ['expires_at'], 'invitations_expires_at_index');

        } else {
            // Table doesn't exist, create it with full structure
            Schema::create('invitations', function (Blueprint $table) {
                $table->id();
                $table->string('token', 64)->unique();
                $table->string('email');
                $table->unsignedBigInteger('invited_by');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->unsignedBigInteger('used_by')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('metadata')->nullable(); // For storing additional data like role, permissions, etc.
                $table->timestamps();

                // Add indexes
                $table->index('invited_by');
                $table->index('used_by');
                $table->index(['token', 'is_active']);
                $table->index(['email', 'is_active']);
                $table->index('expires_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only drop columns we added, don't drop the entire table
        // in case there's existing data from the old system
        if (Schema::hasTable('invitations')) {
            Schema::table('invitations', function (Blueprint $table) {
                // Drop indexes first
                $this->dropIndexSafely('invitations', 'invitations_token_is_active_index');
                $this->dropIndexSafely('invitations', 'invitations_email_is_active_index');
                $this->dropIndexSafely('invitations', 'invitations_expires_at_index');

                // Drop individual indexes
                $this->dropIndexSafely('invitations', 'invitations_invited_by_index');
                $this->dropIndexSafely('invitations', 'invitations_used_by_index');

                // Drop columns we might have added
                $columnsToCheck = ['token', 'email', 'invited_by', 'expires_at', 'used_at', 'used_by', 'is_active', 'metadata'];
                foreach ($columnsToCheck as $column) {
                    if (Schema::hasColumn('invitations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * Safely add an index if it doesn't exist
     */
    private function addIndexSafely(string $table, array $columns, string $indexName): void
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            if (empty($indexes)) {
                Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                    $table->index($columns, $indexName);
                });
            }
        } catch (\Exception $e) {
            // Index creation failed, but continue
        }
    }

    /**
     * Safely drop an index if it exists
     */
    private function dropIndexSafely(string $table, string $indexName): void
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            if (!empty($indexes)) {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        } catch (\Exception $e) {
            // Index drop failed, but continue
        }
    }
};
