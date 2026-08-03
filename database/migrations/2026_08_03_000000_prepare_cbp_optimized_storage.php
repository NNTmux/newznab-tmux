<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('collections') && ! Schema::hasColumn('collections', 'last_seen_at')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->dateTime('last_seen_at')->nullable()->after('dateadded');
            });
        }

        if (! Schema::hasTable('collection_groups')) {
            Schema::create('collection_groups', function (Blueprint $table): void {
                $table->unsignedInteger('collections_id');
                $table->string('group_name', 255);
                $table->primary(['collections_id', 'group_name']);
                $table->foreign('collections_id', 'fk_collection_groups_collection')
                    ->references('id')->on('collections')
                    ->cascadeOnUpdate()->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('collections') && ! $this->indexExists('collections', 'ix_collections_group_filecheck_seen_id')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->index(
                    ['groups_id', 'filecheck', 'last_seen_at', 'id'],
                    'ix_collections_group_filecheck_seen_id'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('collection_groups')) {
            Schema::drop('collection_groups');
        }

        if (Schema::hasTable('collections') && $this->indexExists('collections', 'ix_collections_group_filecheck_seen_id')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->dropIndex('ix_collections_group_filecheck_seen_id');
            });
        }

        if (Schema::hasTable('collections') && Schema::hasColumn('collections', 'last_seen_at')) {
            Schema::table('collections', function (Blueprint $table): void {
                $table->dropColumn('last_seen_at');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return DB::select(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND name = ?",
                [$table, $index]
            ) !== [];
        }

        return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]) !== [];
    }
};
