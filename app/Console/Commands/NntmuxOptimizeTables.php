<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NntmuxOptimizeTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:optimize-tables
    {--t|table=* : Table to optimize or all tables.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks and optimizes tables.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Checking and optimizing tables.');
        $table = $this->option('table');
        if (empty($table) || $table[0] === 'all') {
            $this->optimizeAllTables();
        } else {
            $this->optimizeTable($table); // @phpstan-ignore argument.type
        }
    }

    /**
     * Optimize all tables.
     */
    private function optimizeAllTables(): void
    {
        $tables = DB::select('SHOW TABLES');
        foreach ($tables as $table) {
            $this->optimizeTable($table->Tables_in_nntmux);
        }
    }

    /**
     * Optimize a single table.
     *
     * @param  array<string, mixed>  $tables
     */
    private function optimizeTable(array|string $tables): void
    {
        if (is_array($tables)) {
            foreach ($tables as $table) {
                $this->tableCheck($table);
            }
        } else {
            $this->tableCheck($tables);
        }
    }

    private function tableCheck(mixed $table): void
    {
        $this->info('Checking table: '.$table);
        $tableCheck = DB::select('CHECK TABLE '.$table);
        if ($tableCheck[0]->Msg_text !== 'OK') {
            $this->error('Table '.$table.' is corrupted. Please repair it.');
            $this->info('Optimizing table: '.$table);
            DB::select('OPTIMIZE TABLE '.$table);
        } else {
            $this->info('Table '.$table.' is ok. Optimization is not needed.');
        }
    }
}
