<?php

namespace App\Console\Commands;

use App\Models\Settings;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class InstallNntmux extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'nntmux:install';

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

    /**
     * Execute the console command.
     *
     * @throws \Symfony\Component\Process\Exception\InvalidArgumentException
     * @throws \Symfony\Component\Process\Exception\LogicException
     * @throws \Symfony\Component\Process\Exception\RuntimeException
     * @throws \Exception
     */
    public function handle()
    {
        $error = false;

        if ($this->confirm('Are you sure you want to install NNTmux? This will wipe your database!!')) {
            if (File::exists(base_path().'/_install/install.lock')) {
                if ($this->confirm('Do you want to remove install.lock file so you can continue with install?')) {
                    $this->info('Removing install.lock file so we can continue with install process');
                    $remove = Process::fromShellCommandline('exec rm _install/install.lock');
                    $remove->setTimeout(600);
                    $remove->run(function ($type, $buffer) {
                        if (Process::ERR === $type) {
                            echo 'ERR > '.$buffer;
                        } else {
                            echo $buffer;
                        }
                    });
                } else {
                    $this->info('Not removing install.lock, stopping install process');
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
                $sql1 = Settings::query()->where('setting', '=', 'nzbpath')->update(['value' => $paths['nzb_path']]);
                $sql2 = Settings::query()->where('setting', '=', 'tmpunrarpath')->update(['value' => $paths['unrar_path']]);
                $sql3 = Settings::query()->where('setting', '=', 'coverspath')->update(['value' => $paths['covers_path']]);
                if ($sql1 === null || $sql2 === null || $sql3 === null) {
                    $error = true;
                } else {
                    $this->info('Settings table updated successfully');
                }
            }

            if (! $error && $this->addAdminUser()) {
                File::put(base_path().'/_install/install.lock', 'application install locked on '.now());
                $this->info('Generating application key');
                $this->call('key:generate', ['--force' => true]);
                $this->info('NNTmux installation completed successfully');
                exit();
            }

            $this->error('NNTmux installation failed. Fix reported problems and try again');
        } else {
            $this->info('Stopping install process');
            exit;
        }
    }

    /**
     * @return array|bool
     *
     * @throws \Exception
     * @throws \RuntimeException
     */
    private function updatePaths()
    {
        $covers_path = base_path().'/resources/covers/';
        $nzb_path = base_path().'/resources/nzb/';
        $tmp_path = base_path().'/resources/tmp/';
        $unrar_path = $tmp_path.'unrar/';

        $nzbPathCheck = File::isWritable($nzb_path);
        if (! $nzbPathCheck) {
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
        $unrarPathCheck = is_writable($unrar_path);
        if ($unrarPathCheck === false) {
            $this->warn($unrar_path.' is not writable. Please fix folder permissions');

            return false;
        }

        $coversPathCheck = File::isWritable($covers_path);
        if (! $coversPathCheck) {
            $this->warn($covers_path.' is not writable. Please fix folder permissions');

            return false;
        }

        return [
            'nzb_path' => Str::finish($nzb_path, '/'),
            'covers_path' => Str::finish($covers_path, '/'),
            'unrar_path' => Str::finish($unrar_path, '/'),
        ];
    }

    /**
     * @return bool
     */
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
