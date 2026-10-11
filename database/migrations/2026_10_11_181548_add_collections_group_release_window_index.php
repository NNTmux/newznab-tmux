<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('collections', 'ix_collections_group_filecheck_id')) {
            return;
        }
        $this->alter(static function (): void {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE collections ADD INDEX ix_collections_group_filecheck_id (groups_id, filecheck, id), ALGORITHM=INPLACE, LOCK=NONE');

                return;
            }
            Schema::table('collections', static function (Blueprint $table): void {
                $table->index(['groups_id', 'filecheck', 'id'], 'ix_collections_group_filecheck_id');
            });
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('collections', 'ix_collections_group_filecheck_id')) {
            return;
        }
        $this->alter(static function (): void {
            if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
                DB::statement('ALTER TABLE collections DROP INDEX ix_collections_group_filecheck_id, ALGORITHM=INPLACE, LOCK=NONE');

                return;
            }
            Schema::table('collections', static function (Blueprint $table): void {
                $table->dropIndex('ix_collections_group_filecheck_id');
            });
        });
    }

    private function alter(callable $operation): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $operation();

            return;
        }
        $previous = (int) DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS seconds')->seconds;
        DB::statement('SET SESSION lock_wait_timeout = 30');
        try {
            $operation();
        } finally {
            DB::statement('SET SESSION lock_wait_timeout = '.$previous);
        }
    }
};
