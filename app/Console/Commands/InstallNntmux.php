<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class InstallNntmux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:install {--y|yes : Skip confirmation prompts and proceed with installation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install NNTmux';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $yesMode = $this->option('yes');
        if (File::exists(base_path().'/_install/install.lock')) {
            if ($yesMode) {
                $this->info('Install is locked. The file "install.lock" is present. Use interactive mode to remove it.');
                exit;
            } else {
                if ($this->confirm('Install is locked. Do you want to remove the "install.lock" file to continue?')) {
                    $this->info('Removing install.lock file so we can continue with install process...');
                    $remove = Process::timeout(600)->run('rm _install/install.lock');
                    echo $remove->output();
                    echo $remove->errorOutput();
                } else {
                    $this->info('Installation aborted. The file "install.lock" was not removed.');
                    exit;
                }
            }
        }

        if (! $yesMode) {
            if (! $this->confirm('Are you sure you want to install NNTmux? This will wipe your database!!')) {
                $this->info('Installation aborted by user.');
                exit;
            }
        }

        $this->info('Migrating tables and seeding them with initial data');
        if (config('app.env') !== 'production') {
            $this->call('migrate:fresh', ['--seed' => true]);
        } else {
            $this->call('migrate:fresh', ['--force' => true, '--seed' => true]);
        }

        $paths = $this->updatePaths();
        if ($paths !== false) {
            $this->info('Paths checked successfully');
        }

        $this->setupManticoreConfig();

        if ($this->addAdminUser()) {
            File::put(base_path().'/_install/install.lock', 'application install locked on '.now());
            $this->info('Generating application key');
            $this->call('key:generate', ['--force' => true]);
            $this->info('NNTmux installation completed successfully');
            exit();
        }

        $this->error('NNTmux installation failed. Fix reported problems and try again');

    }

    /**
     * @return array|bool
     *
     * @throws \Exception
     * @throws \RuntimeException
     */
    private function updatePaths()
    {
        $covers_path = config('nntmux_settings.covers_path');
        $nzb_path = config('nntmux_settings.path_to_nzbs');
        $zip_path = config('nntmux_settings.tmp_unzip_path');
        $unrar_path = config('nntmux.tmp_unrar_path');

        // Validate that all required paths are configured
        if (empty($nzb_path)) {
            $this->warn('NZB path (nntmux_settings.path_to_nzbs) is not configured. Please check your .env or config files.');

            return false;
        }

        if (empty($unrar_path)) {
            $this->warn('Unrar path (nntmux.tmp_unrar_path) is not configured. Please check your .env or config files.');

            return false;
        }

        if (empty($covers_path)) {
            $this->warn('Covers path (nntmux_settings.covers_path) is not configured. Please check your .env or config files.');

            return false;
        }

        if (empty($zip_path)) {
            $this->warn('Unzip path (nntmux_settings.tmp_unzip_path) is not configured. Please check your .env or config files.');

            return false;
        }

        if (! File::isWritable($nzb_path)) {
            $this->warn($nzb_path.' is not writable. Please fix folder permissions');

            return false;
        }

        if (! file_exists($unrar_path)) {
            $this->info('Creating missing '.$unrar_path.' folder');
            if (! @File::makeDirectory($unrar_path) && ! File::isDirectory($unrar_path)) {
                throw new \RuntimeException('Unable to create '.$unrar_path.' folder');
            }
            $this->info('Folder '.$unrar_path.' successfully created');
        }

        if (! is_writable($unrar_path)) {
            $this->warn($unrar_path.' is not writable. Please fix folder permissions');

            return false;
        }

        if (! File::isWritable($covers_path)) {
            $this->warn($covers_path.' is not writable. Please fix folder permissions');

            return false;
        }

        if (! File::isWritable($zip_path)) {
            $this->warn($zip_path.' is not writable. Please fix folder permissions');

            return false;
        }

        return true;
    }

    private function addAdminUser(): bool
    {
        if (config('nntmux.admin_username') === '' || config('nntmux.admin_password') === '' || config('nntmux.admin_email') === '') {
            $this->error('Admin user data cannot be empty! Please edit .env file and fill in admin user details and run this script again!');
            exit();
        }

        $this->info('Adding admin user to database');
        try {
            User::add(config('nntmux.admin_username'), config('nntmux.admin_password'), config('nntmux.admin_email'), 2);
            User::where('username', config('nntmux.admin_username'))->update(['verified' => 1, 'email_verified_at' => now()]);
        } catch (\Throwable $e) {
            echo $e->getMessage();
            $this->error('Unable to add admin user!');

            return false;
        }

        return true;
    }

    /**
     * Setup Manticore configuration by creating a symlink from the system config location
     * to the project's config file (for native installations, not Docker).
     */
    private function setupManticoreConfig(): void
    {
        $sourceConfig = base_path('config/manticore.conf');
        $targetConfig = '/etc/manticoresearch/manticore.conf';

        // Skip if running in Docker (config is mounted via docker-compose)
        if (app()->runningInConsole() && getenv('LARAVEL_SAIL')) {
            return;
        }

        if (! File::exists($sourceConfig)) {
            return;
        }

        // Check if target directory exists
        if (! File::isDirectory('/etc/manticoresearch')) {
            $this->warn('Manticore config directory /etc/manticoresearch does not exist. Is Manticore installed?');

            return;
        }

        // Check if symlink or file already exists
        if (File::exists($targetConfig) || is_link($targetConfig)) {
            if (is_link($targetConfig) && readlink($targetConfig) === $sourceConfig) {
                $this->info('Manticore config symlink already exists and points to correct location.');

                return;
            }

            $this->warn("Manticore config already exists at {$targetConfig}.");
            $this->warn("To use the project's config, manually run: sudo ln -sf {$sourceConfig} {$targetConfig}");

            return;
        }

        // Check if we can write to the target directory without sudo
        $needsSudo = ! is_writable('/etc/manticoresearch');

        if ($needsSudo) {
            $this->info('Creating symlink for Manticore configuration requires sudo privileges.');

            if (! $this->confirm('Do you want to create the symlink now? You will be prompted for your password.')) {
                $this->warn("Skipping symlink creation. To create it manually, run: sudo ln -s {$sourceConfig} {$targetConfig}");

                return;
            }

            // Use TTY mode to allow interactive sudo password prompt
            $result = Process::forever()->tty()->run("sudo ln -s {$sourceConfig} {$targetConfig}");
        } else {
            $this->info('Creating symlink for Manticore configuration...');
            $result = Process::run("ln -s {$sourceConfig} {$targetConfig}");
        }

        if ($result->successful()) {
            $this->info("Manticore config symlink created: {$targetConfig} -> {$sourceConfig}");
        } else {
            $this->warn('Could not create Manticore config symlink.');
            $this->warn($result->errorOutput());
            $this->warn("Please run manually: sudo ln -s {$sourceConfig} {$targetConfig}");
        }
    }
}
