<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateNNTmuxDB extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nntmux:db
                            {--seed : Run database seeders after migration}
                            {--rollback= : Rollback the last N migrations}
                            {--check : Check migration status without running}';

    /**
     * The console command description.
     */
    protected $description = 'Update NNTmux database with enhanced safety and performance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('🗄️ Starting database update process...');

            // Check database connection and detect database type
            $dbType = $this->checkDatabaseConnection();
            if (! $dbType) {
                $this->error('Database connection failed');

                return Command::FAILURE;
            }

            // Handle rollback if requested
            if ($this->option('rollback')) {
                return $this->handleRollback();
            }

            // Check migration status if requested
            if ($this->option('check')) {
                return $this->checkMigrationStatus();
            }

            // Run migrations
            $this->info('🔄 Running database migrations...');
            $this->runMigrations();

            // Run seeders if requested
            if ($this->option('seed')) {
                $this->info('🌱 Running database seeders...');
                $this->runSeeders();
            }

            // Optimize database
            $this->info('⚡ Optimizing database...');
            $this->optimizeDatabase($dbType);

            $this->info('✅ Database update completed successfully');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Database update failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Check database connection and return database type
     */
    private function checkDatabaseConnection(): ?string
    {
        try {
            DB::connection()->getPdo();

            $dbConfig = config('database.connections.'.config('database.default'));
            $driver = $dbConfig['driver'] ?? null;

            // Detect actual database type for MySQL-compatible drivers
            if (in_array($driver, ['mysql', 'mariadb'])) {
                $version = DB::select('SELECT VERSION() as version')[0]->version;

                if (str_contains(strtolower($version), 'mariadb')) {
                    $actualType = 'mariadb';
                } else {
                    $actualType = 'mysql';
                }

                $this->line("  ✓ Database connection established ($actualType $version)");

                return $actualType;
            }

            $this->line("  ✓ Database connection established ($driver)");

            return $driver;

        } catch (\Exception $e) {
            $this->error('  ✗ Database connection failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Handle migration rollback
     */
    private function handleRollback(): int
    {
        $steps = (int) $this->option('rollback');

        if ($steps <= 0) {
            $this->error('Invalid rollback steps. Must be a positive integer.');

            return Command::FAILURE;
        }

        $this->warn("⚠️ Rolling back $steps migrations...");

        if (! $this->confirm('Are you sure you want to rollback migrations? This may cause data loss.')) {
            $this->info('Rollback cancelled');

            return Command::SUCCESS;
        }

        $exitCode = $this->call('migrate:rollback', ['--step' => $steps]);

        if ($exitCode === 0) {
            $this->info("✅ Successfully rolled back $steps migrations");
        }

        return $exitCode;
    }

    /**
     * Check migration status
     */
    private function checkMigrationStatus(): int
    {
        $this->info('📊 Migration Status:');

        return $this->call('migrate:status');
    }

    /**
     * Run database migrations
     */
    private function runMigrations(): void
    {
        $migrateOptions = [];

        if (app()->environment('production')) {
            $migrateOptions['--force'] = true;
        }

        $exitCode = $this->call('migrate', $migrateOptions);

        if ($exitCode !== 0) {
            throw new \Exception('Migration failed');
        }

        $this->line('  ✓ Migrations completed successfully');
    }

    /**
     * Run database seeders
     */
    private function runSeeders(): void
    {
        $seedOptions = [];

        if (app()->environment('production')) {
            $seedOptions['--force'] = true;
        }

        $exitCode = $this->call('db:seed', $seedOptions);

        if ($exitCode !== 0) {
            throw new \Exception('Seeding failed');
        }

        $this->line('  ✓ Seeders completed successfully');
    }

    /**
     * Optimize database tables with proper driver detection
     */
    private function optimizeDatabase(string $dbType): void
    {
        try {
            if (in_array($dbType, ['mysql', 'mariadb'])) {
                $this->optimizeMysqlCompatible($dbType);
            } elseif ($dbType === 'pgsql') {
                $this->optimizePostgres();
            } else {
                $this->line("  ℹ Database optimization skipped (unsupported type: $dbType)");
            }
        } catch (\Exception $e) {
            $this->warn('  ⚠ Database optimization failed: '.$e->getMessage());
        }
    }

    /**
     * Optimize MySQL/MariaDB tables
     */
    private function optimizeMysqlCompatible(string $dbType): void
    {
        $dbConfig = config('database.connections.'.config('database.default'));
        $tables = DB::select('SHOW TABLES');
        $tableKey = 'Tables_in_'.$dbConfig['database'];

        $optimizedCount = 0;
        foreach ($tables as $table) {
            $tableName = $table->$tableKey;
            try {
                DB::statement("OPTIMIZE TABLE `$tableName`");
                $optimizedCount++;
            } catch (\Exception $e) {
                $this->warn("    ⚠ Failed to optimize table: $tableName");
            }
        }

        $this->line("  ✓ $dbType tables optimized ($optimizedCount/".count($tables).')');
    }

    /**
     * Optimize PostgreSQL database
     */
    private function optimizePostgres(): void
    {
        try {
            DB::statement('VACUUM ANALYZE');
            $this->line('  ✓ PostgreSQL database optimized (VACUUM ANALYZE)');
        } catch (\Exception $e) {
            $this->warn('  ⚠ PostgreSQL optimization failed: '.$e->getMessage());
        }
    }
}
