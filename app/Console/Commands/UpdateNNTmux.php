<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NntmuxUpdateLock;
use App\Services\Tmux\Tmux;
use Illuminate\Console\Command;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Symfony\Component\Console\Helper\ProgressBar;

class UpdateNNTmux extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'nntmux:all
                            {--skip-git : Skip git operations}
                            {--skip-composer : Skip composer update}
                            {--skip-npm : Skip npm operations}
                            {--skip-db : Skip database migrations}
                            {--git-lock-timeout=10 : Seconds to wait for an existing Git index lock}
                            {--force : Force update even if up-to-date}';

    /**
     * The console command description.
     */
    protected $description = 'Update NNTmux installation with improved performance and error handling';

    /**
     * @var bool Whether the app was in maintenance mode before we started
     */
    private bool $wasInMaintenance = false;

    /**
     * @var bool Whether tmux was running before we started
     */
    private bool $tmuxWasRunning = false;

    /**
     * @var bool Whether composer dependencies were refreshed during this update
     */
    private bool $composerWasRun = false;

    /**
     * @var ProgressBar Progress bar for tracking operations
     */
    private ProgressBar $progressBar;

    /**
     * Execute the console command.
     */
    public function handle(NntmuxUpdateLock $lock): int
    {
        try {
            return $lock->run(fn (): int => $this->updateInstallation(), checkGit: ! $this->option('skip-git'), gitLockTimeout: (int) $this->option('git-lock-timeout'));
        } catch (\Exception $e) {
            $this->error('❌ Update failed before preparing the environment: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function updateInstallation(): int
    {
        $this->wasInMaintenance = false;
        $this->tmuxWasRunning = false;
        $this->composerWasRun = false;
        $this->info('🚀 Starting NNTmux update process...');

        // Initialize progress tracking
        $totalSteps = $this->calculateTotalSteps();
        $this->progressBar = $this->output->createProgressBar($totalSteps);
        $this->progressBar->start();

        try {
            // Prepare environment
            $this->prepareEnvironment();

            // Execute update steps
            $this->executeUpdateSteps();

            // Finalize
            $this->finalizeUpdate();

            $this->progressBar->finish();
            $this->newLine(2);
            $this->info('✅ NNTmux update completed successfully!');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->progressBar->clear();
            $this->newLine(2);
            $this->error('❌ Update failed: '.$e->getMessage());
            $this->restoreEnvironment(startTmux: false);

            return Command::FAILURE;
        }
    }

    /**
     * Calculate total steps for progress tracking
     */
    private function calculateTotalSteps(): int
    {
        $steps = 3; // prepare, finalize, cleanup

        if (! $this->option('skip-git')) {
            $steps++;
        }
        if (! $this->option('skip-composer')) {
            $steps++;
        }
        if (! $this->option('skip-npm')) {
            $steps += 2;
        } // install + build
        if (! $this->option('skip-db')) {
            $steps++;
        }

        $steps++; // env merge

        return $steps;
    }

    /**
     * Prepare the environment for updates
     */
    private function prepareEnvironment(): void
    {
        $this->info('🔧 Preparing environment...');

        // Check if app is in maintenance mode
        $this->wasInMaintenance = App::isDownForMaintenance();
        if (! $this->wasInMaintenance) {
            $this->call('down', [
                '--render' => 'errors::maintenance',
                '--retry' => 120,
                '--secret' => Str::random(40),
            ]);
        }

        // Check if tmux is running
        $tmux = new Tmux;
        $this->tmuxWasRunning = $tmux->isRunning();
        if ($this->call('tmux:stop', ['--force' => true, '--wait' => true, '--timeout' => 60]) !== Command::SUCCESS) {
            throw new \RuntimeException('Failed to stop tmux before updating');
        }

        $this->progressBar->advance();
    }

    /**
     * Execute the main update steps
     */
    private function executeUpdateSteps(): void
    {
        // Git operations
        if (! $this->option('skip-git')) {
            $this->performGitUpdate();
        }

        // Composer operations
        if (! $this->option('skip-composer')) {
            $this->performComposerUpdate();
        }

        // Database migrations
        if (! $this->option('skip-db')) {
            $this->performDatabaseUpdate();
        }

        // NPM operations
        if (! $this->option('skip-npm')) {
            $this->performNpmOperations();
        }

        // Clear caches and perform maintenance
        $this->performMaintenanceTasks();
    }

    /**
     * Perform git update with better error handling
     */
    private function performGitUpdate(): void
    {
        $this->info('📥 Updating from git repository...');

        $gitResult = $this->call('nntmux:git', ['--git-lock-timeout' => $this->option('git-lock-timeout')]);

        if ($gitResult !== 0) {
            throw new \Exception('Git update failed');
        }

        $this->progressBar->advance();
    }

    /**
     * Perform composer update with optimization
     */
    private function performComposerUpdate(): void
    {
        $this->info('📦 Updating composer dependencies...');

        $composerResult = $this->call('nntmux:composer');

        if ($composerResult !== 0) {
            throw new \Exception('Composer update failed');
        }

        $this->composerWasRun = true;

        $this->progressBar->advance();
    }

    /**
     * Perform database updates
     */
    private function performDatabaseUpdate(): void
    {
        $this->info('🗄️ Updating database...');

        $dbResult = $this->composerWasRun
            ? $this->runFreshArtisanCommand(['nntmux:db'])
            : $this->call('nntmux:db');

        if ($dbResult !== 0) {
            throw new \Exception('Database update failed');
        }

        $this->progressBar->advance();
    }

    /**
     * Run an Artisan command in a new PHP process so updated code and dependencies are loaded consistently.
     *
     * @param  list<string>  $arguments
     */
    private function runFreshArtisanCommand(array $arguments): int
    {
        $this->line('  ℹ Restarting Artisan to load updated code...');

        $result = Process::timeout(3600)
            ->path(base_path())
            ->run(array_merge([PHP_BINARY, 'artisan'], $arguments), function (string $type, string $output): void {
                $this->output->write($output);
            });

        if (! $result->successful() && $result->errorOutput() !== '') {
            $this->error($result->errorOutput());
        }

        return $result->exitCode();
    }

    /**
     * Perform NPM operations with parallel processing where possible
     */
    private function performNpmOperations(): void
    {
        $this->info('📦 Installing npm packages...');

        $process = Process::timeout(600)
            ->path(base_path())
            ->run('npm ci --silent');

        if (! $process->successful()) {
            // Fallback to npm install if ci fails
            $this->warn('  ⚠ npm ci failed, falling back to npm install');
            $this->line($this->formatProcessFailure($process, 'npm ci'));
            $process = Process::timeout(600)
                ->path(base_path())
                ->run('npm install --silent');

            if (! $process->successful()) {
                throw new \Exception($this->formatProcessFailure($process, 'npm install'));
            }
        }

        $this->progressBar->advance();

        // Build assets
        $this->info('🔨 Building assets...');

        $typeTransformProcess = Process::timeout(600)
            ->path(base_path())
            ->run('php artisan typescript:transform --force');

        if (! $typeTransformProcess->successful()) {
            throw new \Exception($this->formatProcessFailure($typeTransformProcess, 'TypeScript transform'));
        }

        $viteBuildProcess = Process::timeout(600)
            ->path(base_path())
            ->run('npm exec vite build');

        if (! $viteBuildProcess->successful()) {
            throw new \Exception($this->formatProcessFailure($viteBuildProcess, 'Vite build'));
        }

        $this->progressBar->advance();
    }

    /**
     * Perform maintenance tasks
     */
    private function performMaintenanceTasks(): void
    {
        // Refresh config cache so runtime client/host settings cannot drift.
        $this->info('🧹 Clearing config cache...');
        $this->call('config:clear');
        $this->info('Caching config...');
        $this->call('config:cache');

        // Clear view cache
        $this->info('🧹 Clearing view cache...');
        $this->call('view:clear');
        $this->info('Clearing route cache...');
        $this->call('route:clear');
        $this->info('Cache cleared successfully');
        $this->info('Caching routes...');
        $this->call('route:cache');
        $this->info('Routes cached successfully');

        // Merge environment variables
        $this->info('⚙️ Merging environment configuration...');
        $this->mergeEnvironmentConfig();
        $this->progressBar->advance();
    }

    /**
     * Merge environment configuration with improved error handling
     */
    private function mergeEnvironmentConfig(): void
    {
        try {
            $envExamplePath = base_path('.env.example');
            $envPath = base_path('.env');

            if (! File::exists($envExamplePath)) {
                $this->warn('  ⚠ .env.example not found, skipping environment merge');

                return;
            }

            if (! File::exists($envPath)) {
                $this->warn('  ⚠ .env not found, skipping environment merge');

                return;
            }

            $envExampleVars = $this->parseEnvFile($envExamplePath);
            $envVars = $this->parseEnvFile($envPath);

            $missingKeys = array_diff_key($envExampleVars, $envVars);

            if (empty($missingKeys)) {
                $this->line('  ✓ No new environment variables to merge');

                return;
            }

            $this->addMissingEnvVars($envPath, $missingKeys);
            $this->line('  ✓ Merged '.count($missingKeys).' new environment variables');

        } catch (\Exception $e) {
            $this->warn('  ⚠ Environment merge failed: '.$e->getMessage());
        }
    }

    /**
     * Parse environment file into key-value pairs
     *
     * @return array<string, mixed>
     */
    private function parseEnvFile(string $path): array
    {
        $content = File::get($path);
        $vars = [];

        foreach (preg_split("/\r\n|\n|\r/", $content) as $line) {
            $line = trim($line);

            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = $matches[2];
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    /**
     * Add missing environment variables to .env file
     *
     * @param  array<string, mixed>  $missingKeys
     */
    private function addMissingEnvVars(string $envPath, array $missingKeys): void
    {
        $content = File::get($envPath);

        if (! str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        $content .= "\n# New settings added from .env.example on ".now()->toDateTimeString()."\n";

        foreach ($missingKeys as $key => $value) {
            $content .= "$key=$value\n";
        }

        File::put($envPath, $content);
    }

    /**
     * Finalize the update process
     */
    private function finalizeUpdate(): void
    {
        $this->info('🏁 Finalizing update...');

        // Clear application caches
        Cache::flush();

        // Fail fast if production runtime drifts away from phpredis.
        $this->ensureRedisRuntimeIsHealthy();

        // Restore application state
        $this->restoreEnvironment(startTmux: true);

        $this->progressBar->advance();
    }

    /**
     * Restore the original environment state
     */
    private function restoreEnvironment(bool $startTmux): void
    {
        // Restore maintenance mode state
        if (! $this->wasInMaintenance && App::isDownForMaintenance()) {
            $this->call('up');
        }

        // Restore tmux state
        if ($this->tmuxWasRunning && $startTmux) {
            $this->info('🔁 Restoring tmux processing session...');
            // tmux:stop ran before the update and loaded the old Tmux classes into this process.
            $tmuxStartResult = $this->runFreshArtisanCommand(['tmux:start']);

            if ($tmuxStartResult !== Command::SUCCESS) {
                throw new \Exception('tmux:start failed while restoring environment');
            }
        } elseif ($this->tmuxWasRunning) {
            $this->warn('  ⚠ Skipping tmux restart because update did not complete successfully');
        }
    }

    /**
     * Enforce production Redis client expectations and basic connectivity.
     */
    private function ensureRedisRuntimeIsHealthy(): void
    {
        $redisClient = strtolower((string) config('database.redis.client', ''));
        if ($redisClient !== 'phpredis') {
            throw new \Exception(
                "Redis client mismatch detected: runtime uses '{$redisClient}', expected 'phpredis'. ".
                'Check REDIS_CLIENT in environment, clear config cache, and verify deployment configuration.'
            );
        }

        if (! extension_loaded('redis')) {
            throw new \Exception(
                "Redis PHP extension is not loaded while runtime expects 'phpredis'. ".
                'Install/enable ext-redis before restarting workers.'
            );
        }

        try {
            $pong = Redis::connection()->ping();
            $isHealthy = $pong === true || $pong === '+PONG' || strtoupper((string) $pong) === 'PONG';
            if (! $isHealthy) {
                throw new \Exception('Redis ping returned unexpected response: '.(string) $pong);
            }
        } catch (\Throwable $e) {
            throw new \Exception('Redis preflight failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Build actionable process diagnostics for failed shell commands.
     */
    private function formatProcessFailure(ProcessResult $process, string $context): string
    {
        $stdout = trim($process->output());
        $stderr = trim($process->errorOutput());

        $details = [
            "{$context} failed",
            'exit_code='.($process->exitCode() ?? 'n/a'),
        ];

        if ($stderr !== '') {
            $details[] = 'stderr='.$stderr;
        }

        if ($stdout !== '') {
            $details[] = 'stdout='.$stdout;
        }

        if ($stderr === '' && $stdout === '') {
            $details[] = 'no stdout/stderr captured (process may have been terminated or timed out)';
        }

        return implode(' | ', $details);
    }
}
