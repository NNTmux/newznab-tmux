<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class DeployInitialize extends Command
{
    protected $signature = 'nntmux:deploy-init';

    protected $description = 'Initialize an empty deployment database without replacing data or the application key';

    public function handle(): int
    {
        $marker = base_path('_install/install.lock');
        $lock = null;

        try {
            File::ensureDirectoryExists(dirname($marker), 0770);
            $lock = fopen(dirname($marker).'/deploy-init.lock', 'c');
            if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new RuntimeException('Another initialization is running.');
            }

            if (File::exists($marker)) {
                throw new RuntimeException('This installation is already initialized.');
            }

            $this->validateConfiguration();
            if (Schema::getTableListing() !== [] || Schema::getViews() !== []) {
                throw new RuntimeException('Initialization requires an empty database. Existing tables or views will not be changed.');
            }

            foreach ([storage_path('app/public'), storage_path('framework/cache/data'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), config('nntmux_settings.covers_path'), config('nntmux_settings.path_to_nzbs'), config('nntmux.tmp_unzip_path'), config('nntmux.tmp_unrar_path')] as $directory) {
                if (! is_string($directory) || $directory === '') {
                    throw new RuntimeException('Deployment storage paths must be configured.');
                }
                File::ensureDirectoryExists($directory, 0770);
                if (! is_writable($directory)) {
                    throw new RuntimeException('Deployment storage paths must be writable.');
                }
            }

            if ($this->call('migrate', ['--force' => true, '--seed' => true, '--no-interaction' => true]) !== self::SUCCESS) {
                throw new RuntimeException('Migration or seeding failed; initialization remains incomplete.');
            }

            $this->createAdministrator();
            $admin = DB::table('users')->where('username', config('nntmux.admin_username'))->first();
            if ($admin === null || (int) $admin->roles_id !== 2 || $admin->email_verified_at === null || ! DB::table('settings')->where('name', 'categorizeforeign')->exists()) {
                throw new RuntimeException('Administrator or settings verification failed.');
            }

            if ($this->call('manticore:create-indexes', ['--no-interaction' => true]) !== self::SUCCESS) {
                throw new RuntimeException('Search initialization failed; initialization remains incomplete.');
            }

            if (File::put($marker, 'deployment initialized on '.now()) === false) {
                throw new RuntimeException('Unable to record successful initialization.');
            }

            $this->info('Deployment initialized. The supplied application key was preserved.');

            return self::SUCCESS;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Initialization failed. No success marker was written. Inspect the deployment and restore an empty database before retrying.');

            return self::FAILURE;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    private function validateConfiguration(): void
    {
        $key = config('app.key');
        if (! is_string($key)) {
            throw new RuntimeException('A valid APP_KEY must be supplied before initialization.');
        }
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        if ($decoded === false || ! Encrypter::supported($decoded, config('app.cipher'))) {
            throw new RuntimeException('A valid APP_KEY must be supplied before initialization.');
        }

        $username = config('nntmux.admin_username');
        $password = config('nntmux.admin_password');
        $email = config('nntmux.admin_email');
        if (! is_string($username) || trim($username) === '' || ! is_string($password) || strlen($password) < 12 || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Configure ADMIN_USER, ADMIN_EMAIL, and an ADMIN_PASS of at least 12 characters.');
        }
    }

    protected function createAdministrator(): void
    {
        DB::transaction(function (): void {
            $id = User::add(config('nntmux.admin_username'), config('nntmux.admin_password'), config('nntmux.admin_email'), 2);
            User::findOrFail($id)->markEmailAsVerified();
        });
    }
}
