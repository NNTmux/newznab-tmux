<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

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
            $this->optimizeTable($table);
        }
    }

    /**
     * Optimize all tables.
     */
    private function optimizeAllTables(): void
    {
        $tables = \DB::select('SHOW TABLES');
        foreach ($tables as $table) {
            $this->optimizeTable($table->Tables_in_nntmux);
        }
    }

    /**
     * Optimize a single table.
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

    private function tableCheck($table): void
    {
        $this->info('Checking table: '.$table);
        $tableCheck = \DB::select('CHECK TABLE '.$table);
            if ($tableCheck[0]->Msg_text !== 'Ok') {
            $this->error('Table '.$table.' is corrupted. Please repair it.');
            $this->info('Optimizing table: '.$table);
            \DB::statement('OPTIMIZE TABLE '.$table);
        } else {
            $this->info('Table '.$table.' is ok. Optimization is not needed.');
        }
    }
}
