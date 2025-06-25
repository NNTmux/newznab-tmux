<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class UpdateNNTmuxComposer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:composer
                            {--no-dev : Skip development dependencies}
                            {--optimize : Optimize autoloader}
                            {--prefer-dist : Prefer distribution packages}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update composer dependencies with optimizations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            $this->info('📦 Starting composer update process...');

            // Check if composer.json exists
            if (!File::exists(base_path('composer.json'))) {
                $this->error('composer.json not found');
                return Command::FAILURE;
            }

            // Check if composer.lock exists to determine install vs update
            $hasLockFile = File::exists(base_path('composer.lock'));

            if ($hasLockFile) {
                $this->info('🔄 Installing dependencies from lock file...');
                $this->composerInstall();
            } else {
                $this->info('🆕 Creating new lock file and installing dependencies...');
                $this->composerUpdate();
            }

            // Clear autoloader cache
            $this->info('🧹 Clearing autoloader cache...');
            $this->clearAutoloaderCache();

            $this->info('✅ Composer update completed successfully');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Composer update failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * Run composer install
     */
    private function composerInstall(): void
    {
        $command = $this->buildComposerCommand('install');

        $process = Process::timeout(600)
            ->path(base_path())
            ->run($command);

        if (!$process->successful()) {
            throw new \Exception('Composer install failed: ' . $process->errorOutput());
        }

        $this->line('  ✓ Dependencies installed successfully');
    }

    /**
     * Run composer update
     */
    private function composerUpdate(): void
    {
        $command = $this->buildComposerCommand('update');

        $process = Process::timeout(600)
            ->path(base_path())
            ->run($command);

        if (!$process->successful()) {
            throw new \Exception('Composer update failed: ' . $process->errorOutput());
        }

        $this->line('  ✓ Dependencies updated successfully');
    }

    /**
     * Build composer command with options
     */
    private function buildComposerCommand(string $action): string
    {
        $command = "composer $action";

        // Add common flags for performance
        $command .= ' --no-interaction --no-progress';

        if ($this->option('no-dev')) {
            $command .= ' --no-dev';
        }

        if ($this->option('prefer-dist')) {
            $command .= ' --prefer-dist';
        }

        if ($this->option('optimize') || app()->environment('production')) {
            $command .= ' --optimize-autoloader --classmap-authoritative';
        }

        return $command;
    }

    /**
     * Clear autoloader cache
     */
    private function clearAutoloaderCache(): void
    {
        $process = Process::timeout(30)
            ->path(base_path())
            ->run('composer dump-autoload --optimize');

        if (!$process->successful()) {
            $this->warn('  ⚠ Failed to optimize autoloader');
        } else {
            $this->line('  ✓ Autoloader optimized');
        }
    }
}
