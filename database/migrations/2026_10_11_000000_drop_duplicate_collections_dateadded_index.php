<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * collections_dateadded_idx is an exact copy of ix_collection_dateadded (both index
 * dateadded alone), so every collection write maintained it twice for no benefit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('collections', 'collections_dateadded_idx')) {
            return;
        }
        $this->withShortLockWait(static function (): void {
            Schema::table('collections', static function (Blueprint $table): void {
                $table->dropIndex('collections_dateadded_idx');
            });
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('collections', 'collections_dateadded_idx')) {
            return;
        }
        Schema::table('collections', static function (Blueprint $table): void {
            $table->index(['dateadded'], 'collections_dateadded_idx');
        });
    }

    /**
     * Dropping an index needs a brief metadata lock. On a busy table, waiting for it
     * would queue every new query behind the ALTER, so give up after 30 seconds instead
     * (rerun the migration when ingestion is quieter).
     */
    private function withShortLockWait(callable $alter): void
    {
        if (! \in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $alter();

            return;
        }
        $previous = DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS seconds')->seconds;
        DB::statement('SET SESSION lock_wait_timeout = 30');
        try {
            $alter();
        } finally {
            DB::statement('SET SESSION lock_wait_timeout = '.(int) $previous);
        }
    }
};
