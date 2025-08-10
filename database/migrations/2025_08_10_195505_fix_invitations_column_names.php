<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // First, check if we have the old column structure
            if (Schema::hasColumn('invitations', 'users_id') && ! Schema::hasColumn('invitations', 'invited_by')) {
                // Drop the existing foreign key constraint first
                try {
                    $table->dropForeign('FK_users_inv');
                } catch (\Exception $e) {
                    // Foreign key might not exist or have different name
                }

                // Rename the column from users_id to invited_by
                $table->renameColumn('users_id', 'invited_by');
            }

            // Add missing columns that our system needs
            if (! Schema::hasColumn('invitations', 'token')) {
                $table->string('token', 64)->unique()->after('id');
            }

            if (! Schema::hasColumn('invitations', 'email')) {
                $table->string('email')->after('token');
            }

            if (! Schema::hasColumn('invitations', 'expires_at')) {
                $table->timestamp('expires_at')->after('invited_by');
            }

            if (! Schema::hasColumn('invitations', 'used_at')) {
                $table->timestamp('used_at')->nullable()->after('expires_at');
            }

            if (! Schema::hasColumn('invitations', 'used_by')) {
                $table->unsignedBigInteger('used_by')->nullable()->after('used_at');
                $table->index('used_by');
            }

            if (! Schema::hasColumn('invitations', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('used_by');
            }

            if (! Schema::hasColumn('invitations', 'metadata')) {
                $table->json('metadata')->nullable()->after('is_active');
            }

            // Add timestamps if they don't exist
            if (! Schema::hasColumn('invitations', 'created_at')) {
                $table->timestamps();
            }
        });

        // Add indexes safely
        $this->addIndexSafely('invitations', ['invited_by'], 'invitations_invited_by_index');
        $this->addIndexSafely('invitations', ['token', 'is_active'], 'invitations_token_is_active_index');
        $this->addIndexSafely('invitations', ['email', 'is_active'], 'invitations_email_is_active_index');
        $this->addIndexSafely('invitations', ['expires_at'], 'invitations_expires_at_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Drop indexes first
            $this->dropIndexSafely('invitations', 'invitations_invited_by_index');
            $this->dropIndexSafely('invitations', 'invitations_token_is_active_index');
            $this->dropIndexSafely('invitations', 'invitations_email_is_active_index');
            $this->dropIndexSafely('invitations', 'invitations_expires_at_index');
            $this->dropIndexSafely('invitations', 'invitations_used_by_index');

            // Rename back to original column name if it was renamed
            if (Schema::hasColumn('invitations', 'invited_by') && ! Schema::hasColumn('invitations', 'users_id')) {
                $table->renameColumn('invited_by', 'users_id');
            }

            // Drop columns we might have added
            $columnsToCheck = ['token', 'email', 'expires_at', 'used_at', 'used_by', 'is_active', 'metadata'];
            foreach ($columnsToCheck as $column) {
                if (Schema::hasColumn('invitations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
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
            if (! empty($indexes)) {
                Schema::table($table, function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        } catch (\Exception $e) {
            // Index drop failed, but continue
        }
    }
};
