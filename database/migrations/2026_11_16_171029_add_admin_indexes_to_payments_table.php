<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index name => column, for the admin payments list and the BTCPay webhook lookup.
     *
     * @var array<string, string>
     */
    private array $indexes = [
        'ix_payments_created_at' => 'created_at',
        'ix_payments_invoice_id' => 'invoice_id',
        'ix_payments_username' => 'username',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        foreach ($this->indexes as $indexName => $column) {
            if (! $this->indexExists($indexName)) {
                Schema::table('payments', function (Blueprint $table) use ($column, $indexName): void {
                    $table->index($column, $indexName);
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        foreach (array_keys($this->indexes) as $indexName) {
            if ($this->indexExists($indexName)) {
                Schema::table('payments', function (Blueprint $table) use ($indexName): void {
                    $table->dropIndex($indexName);
                });
            }
        }
    }

    private function indexExists(string $indexName): bool
    {
        try {
            return Schema::hasIndex('payments', $indexName);
        } catch (Throwable) {
            if (DB::getDriverName() === 'sqlite') {
                foreach (DB::select("PRAGMA index_list('payments')") as $index) {
                    if (($index->name ?? null) === $indexName) {
                        return true;
                    }
                }

                return false;
            }
        }

        return count(DB::select('SHOW INDEX FROM `payments` WHERE Key_name = ?', [$indexName])) > 0;
    }
};
