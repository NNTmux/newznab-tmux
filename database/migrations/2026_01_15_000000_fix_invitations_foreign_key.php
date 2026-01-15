<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration fixes the invitations table by:
     * 1. Dropping the old FK_users_inv foreign key constraint
     * 2. Renaming users_id to invited_by if needed
     * 3. Adding missing columns for the new invitation system
     */
    public function up(): void
    {
        // First, drop the old foreign key constraint if it exists
        $this->dropForeignKeySafely('invitations', 'FK_users_inv');

        Schema::table('invitations', function (Blueprint $table) {
            // Rename users_id to invited_by if needed
            if (Schema::hasColumn('invitations', 'users_id') && ! Schema::hasColumn('invitations', 'invited_by')) {
                $table->renameColumn('users_id', 'invited_by');
            }
        });

        // Now add missing columns in a separate Schema call (after rename)
        Schema::table('invitations', function (Blueprint $table) {
            // Drop the old guid column if it exists (replaced by token)
            if (Schema::hasColumn('invitations', 'guid') && Schema::hasColumn('invitations', 'token')) {
                $table->dropColumn('guid');
            }

            // Add token if it doesn't exist
            if (! Schema::hasColumn('invitations', 'token')) {
                $table->string('token', 64)->unique()->after('id');
            }

            // Add email if it doesn't exist
            if (! Schema::hasColumn('invitations', 'email')) {
                $table->string('email')->after('token');
            }

            // Add expires_at if it doesn't exist
            if (! Schema::hasColumn('invitations', 'expires_at')) {
                if (Schema::hasColumn('invitations', 'invited_by')) {
                    $table->timestamp('expires_at')->after('invited_by');
                } else {
                    $table->timestamp('expires_at')->nullable();
                }
            }

            // Add used_at if it doesn't exist
            if (! Schema::hasColumn('invitations', 'used_at')) {
                $table->timestamp('used_at')->nullable();
            }

            // Add used_by if it doesn't exist
            if (! Schema::hasColumn('invitations', 'used_by')) {
                $table->unsignedBigInteger('used_by')->nullable();
            }

            // Add is_active if it doesn't exist
            if (! Schema::hasColumn('invitations', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            // Add metadata if it doesn't exist
            if (! Schema::hasColumn('invitations', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });

        // Migrate data from guid to token if guid still exists and has data
        if (Schema::hasColumn('invitations', 'guid') && Schema::hasColumn('invitations', 'token')) {
            DB::table('invitations')
                ->whereNull('token')
                ->orWhere('token', '')
                ->update(['token' => DB::raw('guid')]);

            // Now drop guid
            Schema::table('invitations', function (Blueprint $table) {
                $table->dropColumn('guid');
            });
        }

        // Add indexes safely
        $this->addIndexSafely('invitations', ['invited_by'], 'invitations_invited_by_index');
        $this->addIndexSafely('invitations', ['used_by'], 'invitations_used_by_index');
        $this->addIndexSafely('invitations', ['token', 'is_active'], 'invitations_token_is_active_index');
        $this->addIndexSafely('invitations', ['email', 'is_active'], 'invitations_email_is_active_index');
        $this->addIndexSafely('invitations', ['expires_at'], 'invitations_expires_at_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes first
        $this->dropIndexSafely('invitations', 'invitations_invited_by_index');
        $this->dropIndexSafely('invitations', 'invitations_used_by_index');
        $this->dropIndexSafely('invitations', 'invitations_token_is_active_index');
        $this->dropIndexSafely('invitations', 'invitations_email_is_active_index');
        $this->dropIndexSafely('invitations', 'invitations_expires_at_index');

        Schema::table('invitations', function (Blueprint $table) {
            // Rename back to original column name
            if (Schema::hasColumn('invitations', 'invited_by') && ! Schema::hasColumn('invitations', 'users_id')) {
                $table->renameColumn('invited_by', 'users_id');
            }
        });

        // Re-add the foreign key constraint
        Schema::table('invitations', function (Blueprint $table) {
            if (Schema::hasColumn('invitations', 'users_id')) {
                $table->foreign('users_id', 'FK_users_inv')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            }
        });
    }

    /**
     * Safely drop a foreign key if it exists
     */
    private function dropForeignKeySafely(string $table, string $foreignKeyName): void
    {
        try {
            $foreignKeys = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
                 WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                 AND TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = ?
                 AND CONSTRAINT_NAME = ?",
                [$table, $foreignKeyName]
            );

            if (! empty($foreignKeys)) {
                Schema::table($table, function (Blueprint $table) use ($foreignKeyName) {
                    $table->dropForeign($foreignKeyName);
                });
            }
        } catch (\Exception $e) {
            // Foreign key might not exist, continue
            report($e);
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
            report($e);
        }
    }

    /**
     * Safely drop an index if it exists
     */
    private function dropIndexSafely(string $table, string $indexName): void
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            if (! empty($indexes)) {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        } catch (\Exception $e) {
            // Index drop failed, but continue
            report($e);
        }
    }
};
