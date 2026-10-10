<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\NntmuxUpdateLock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class UpdateNNTmuxGit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:git
                            {--branch= : Specific branch to checkout}
                            {--no-stash : Skip stashing local changes}
                            {--git-lock-timeout=10 : Seconds to wait for an existing Git index lock}
                            {--force : Force pull even if there are conflicts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update NNTmux from git repository with improved error handling';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(NntmuxUpdateLock $lock): int
    {
        try {
            return $lock->run(fn (): int => $this->updateRepository(), gitLockTimeout: (int) $this->option('git-lock-timeout'));
        } catch (\Exception $e) {
            $this->error('❌ Git update failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    private function updateRepository(): int
    {
        $this->info('🔄 Starting git update process...');

        // Check if we're in a git repository
        if (! $this->isGitRepository()) {
            $this->error('Not in a git repository');

            return Command::FAILURE;
        }

        // Check for uncommitted changes
        if ($this->hasUncommittedChanges() && ! $this->option('no-stash')) {
            $this->info('📦 Stashing local changes...');
            $this->stashChanges();
        }

        // Get current branch
        $currentBranch = $this->getCurrentBranch();
        $targetBranch = $this->option('branch') ?? $currentBranch;

        // Fetch latest changes
        $this->info('📡 Fetching latest changes...');
        $this->fetchChanges();

        // Check if update is needed
        if (! $this->option('force') && ! $this->isUpdateNeeded($targetBranch)) {
            $this->info('✅ Already up-to-date');

            return Command::SUCCESS;
        }

        // Perform the update
        $this->info("🔄 Updating to latest $targetBranch...");
        $this->pullChanges($targetBranch);

        $this->info('✅ Git update completed successfully');

        return Command::SUCCESS;
    }

    /**
     * Check if current directory is a git repository
     */
    private function isGitRepository(): bool
    {
        return File::exists(base_path('.git'));
    }

    /**
     * Check if there are uncommitted changes
     */
    private function hasUncommittedChanges(): bool
    {
        $process = Process::path(base_path())->run(['git', '--no-optional-locks', 'status', '--porcelain', '--untracked-files=no']);

        if (! $process->successful()) {
            throw new \Exception('Failed to check local changes: '.$process->errorOutput());
        }

        return ! empty(trim($process->output()));
    }

    /**
     * Stash uncommitted changes
     */
    private function stashChanges(): void
    {
        $process = Process::path(base_path())->timeout(300)->run(['git', 'stash', 'push', '-m', 'Auto-stash before update on '.now()->toDateTimeString()]);

        if (! $process->successful()) {
            throw new \Exception('Failed to stash changes: '.$process->errorOutput());
        }

        $this->line('  ✓ Changes stashed successfully');
    }

    /**
     * Get current git branch
     */
    private function getCurrentBranch(): string
    {
        $process = Process::path(base_path())->run(['git', 'branch', '--show-current']);

        if (! $process->successful()) {
            throw new \Exception('Failed to get current branch: '.$process->errorOutput());
        }

        return trim($process->output());
    }

    /**
     * Fetch latest changes from remote
     */
    private function fetchChanges(): void
    {
        $process = Process::path(base_path())->timeout(300)->run(['git', 'fetch', '--prune']);

        if (! $process->successful()) {
            throw new \Exception('Failed to fetch changes: '.$process->errorOutput());
        }
    }

    /**
     * Check if update is needed
     */
    private function isUpdateNeeded(string $branch): bool
    {
        $process = Process::path(base_path())->run(['git', 'rev-list', "HEAD...origin/$branch", '--count']);

        if (! $process->successful()) {
            // If we can't check, assume update is needed
            return true;
        }

        return (int) trim($process->output()) > 0;
    }

    /**
     * Pull changes from remote
     */
    private function pullChanges(string $branch): void
    {
        $pullCommand = $this->option('force')
            ? ['git', 'reset', '--hard', "origin/$branch"]
            : ['git', 'pull', 'origin', $branch];

        $process = Process::path(base_path())->timeout(300)->run($pullCommand);

        if (! $process->successful()) {
            throw new \Exception('Failed to pull changes: '.$process->errorOutput());
        }

        $this->line('  ✓ Changes pulled successfully');
    }
}
