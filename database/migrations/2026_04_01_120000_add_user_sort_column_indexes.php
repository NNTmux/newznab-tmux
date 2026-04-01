<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on users columns used for ORDER BY in the admin user list.
 *
 * The admin user list supports sorting by these columns; without indexes
 * the database must filesort the entire table before applying LIMIT.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $indexes = [
            'ix_users_username' => 'username',
            'ix_users_email' => 'email',
            'ix_users_host' => 'host',
            'ix_users_lastlogin' => 'lastlogin',
            'ix_users_apiaccess' => 'apiaccess',
            'ix_users_grabs' => 'grabs',
            'ix_users_rolechangedate' => 'rolechangedate',
            'ix_users_verified' => 'verified',
        ];

        foreach ($indexes as $indexName => $column) {
            if (! $this->indexExists('users', $indexName)) {
                Schema::table('users', function (Blueprint $table) use ($indexName, $column) {
                    $table->index($column, $indexName);
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $indexes = [
            'ix_users_username',
            'ix_users_email',
            'ix_users_host',
            'ix_users_lastlogin',
            'ix_users_apiaccess',
            'ix_users_grabs',
            'ix_users_rolechangedate',
            'ix_users_verified',
        ];

        foreach ($indexes as $indexName) {
            if ($this->indexExists('users', $indexName)) {
                Schema::table('users', function (Blueprint $table) use ($indexName) {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }
};
