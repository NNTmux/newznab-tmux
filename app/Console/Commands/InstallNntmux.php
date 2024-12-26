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
}
