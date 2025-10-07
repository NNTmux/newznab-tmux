<?php

namespace App\Console\Commands;

use Blacklight\Tmux;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
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
     * @var ProgressBar Progress bar for tracking operations
     */
    private ProgressBar $progressBar;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
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
            $this->progressBar->finish();
            $this->newLine(2);
            $this->error('❌ Update failed: '.$e->getMessage());
            $this->restoreEnvironment();

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
                '--secret' => config('app.key'),
            ]);
        }

        // Check if tmux is running
        $tmux = new Tmux;
        $this->tmuxWasRunning = $tmux->isRunning();
        if ($this->tmuxWasRunning) {
            $this->call('tmux-ui:stop', ['--kill' => true]);
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

        $gitResult = $this->call('nntmux:git');

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

        $this->progressBar->advance();
    }

    /**
     * Perform database updates
     */
    private function performDatabaseUpdate(): void
    {
        $this->info('🗄️ Updating database...');

        $dbResult = $this->call('nntmux:db');

        if ($dbResult !== 0) {
            throw new \Exception('Database update failed');
        }

        $this->progressBar->advance();
    }

    /**
     * Perform NPM operations with parallel processing where possible
     */
    private function performNpmOperations(): void
    {
        // Check if package.json has changed
        $packageLockExists = File::exists(base_path('package-lock.json'));
        $shouldInstall = ! $packageLockExists || $this->option('force');

        if ($shouldInstall) {
            $this->info('📦 Installing npm packages...');

            $process = Process::timeout(600)
                ->path(base_path())
                ->run('npm ci --silent');

            if (! $process->successful()) {
                // Fallback to npm install if ci fails
                $process = Process::timeout(600)
                    ->path(base_path())
                    ->run('npm install --silent');

                if (! $process->successful()) {
                    throw new \Exception('NPM install failed: '.$process->errorOutput());
                }
            }
        }

        $this->progressBar->advance();

        // Build assets
        $this->info('🔨 Building assets...');

        $buildProcess = Process::timeout(600)
            ->path(base_path())
            ->run('npm run build');

        if (! $buildProcess->successful()) {
            throw new \Exception('Asset build failed: '.$buildProcess->errorOutput());
        }

        $this->progressBar->advance();
    }

    /**
     * Perform maintenance tasks
     */
    private function performMaintenanceTasks(): void
    {
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

        // Restore application state
        $this->restoreEnvironment();

        $this->progressBar->advance();
    }

    /**
     * Restore the original environment state
     */
    private function restoreEnvironment(): void
    {
        // Restore maintenance mode state
        if (! $this->wasInMaintenance && App::isDownForMaintenance()) {
            $this->call('up');
        }

        // Restore tmux state
        if ($this->tmuxWasRunning) {
            $this->call('tmux-ui:start');
        }
    }
}
