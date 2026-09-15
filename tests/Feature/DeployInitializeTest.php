<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\DeployInitialize;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class DeployInitializeTest extends TestCase
{
    private string $directory;

    private string $originalBasePath;

    private string $originalStoragePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.testing.database'));
        Schema::dropAllTables();
        $this->directory = sys_get_temp_dir().'/nntmux-deploy-init-'.bin2hex(random_bytes(8));
        $this->originalBasePath = $this->app->basePath();
        $this->originalStoragePath = $this->app->storagePath();
        $this->app->setBasePath($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        File::ensureDirectoryExists($this->directory);
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.cipher' => 'AES-256-CBC',
            'nntmux.admin_username' => 'deployment-admin',
            'nntmux.admin_password' => 'a-long-test-password',
            'nntmux.admin_email' => 'admin@example.com',
            'nntmux_settings.covers_path' => $this->directory.'/storage/covers',
            'nntmux_settings.path_to_nzbs' => $this->directory.'/storage/nzb',
            'nntmux.tmp_unzip_path' => $this->directory.'/storage/tmp/unzip',
            'nntmux.tmp_unrar_path' => $this->directory.'/storage/tmp/unrar',
        ]);
        File::put($this->directory.'/.env', 'APP_KEY='.config('app.key'));
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_existing_tables_are_preserved_and_migrations_never_run(): void
    {
        Schema::create('existing_data', function (Blueprint $table): void {
            $table->string('value');
        });
        DB::table('existing_data')->insert(['value' => 'keep me']);
        $command = $this->command();
        $command->shouldReceive('call')->never();
        [$status, $output] = $this->runCommand($command);
        $this->assertSame(1, $status);
        $this->assertStringContainsString('empty database', $output);
        $this->assertSame('keep me', DB::table('existing_data')->value('value'));
        $this->assertFileDoesNotExist($this->marker());
    }

    public function test_existing_marker_prevents_reinitialization(): void
    {
        File::ensureDirectoryExists(dirname($this->marker()));
        File::put($this->marker(), 'original marker');
        $command = $this->command();
        $command->shouldReceive('call')->never();
        $this->assertSame(1, $this->runCommand($command)[0]);
        $this->assertSame('original marker', File::get($this->marker()));
    }

    public function test_invalid_key_or_administrator_credentials_fail_before_migrations(): void
    {
        foreach ([['app.key' => 'invalid'], ['nntmux.admin_password' => 'admin'], ['nntmux.admin_email' => 'invalid']] as $invalid) {
            $original = config(array_key_first($invalid));
            config($invalid);
            $command = $this->command();
            $command->shouldReceive('call')->never();
            $this->assertSame(1, $this->runCommand($command)[0]);
            $this->assertFileDoesNotExist($this->marker());
            config([array_key_first($invalid) => $original]);
        }
    }

    public function test_success_records_marker_and_preserves_supplied_key_and_environment(): void
    {
        $key = config('app.key');
        $environment = File::get($this->directory.'/.env');
        $command = $this->command();
        $this->expectMigration($command);
        $this->expectAdministrator($command);
        $command->shouldReceive('call')->once()->with('manticore:create-indexes', ['--no-interaction' => true])->andReturn(0);
        $this->assertSame(0, $this->runCommand($command)[0]);
        $this->assertFileExists($this->marker());
        $this->assertSame($key, config('app.key'));
        $this->assertSame($environment, File::get($this->directory.'/.env'));
        $this->assertDirectoryExists($this->directory.'/storage/covers');
        $this->assertDirectoryExists($this->directory.'/storage/tmp/unzip');
    }

    public function test_failed_migration_is_not_acknowledged_or_retried_destructively(): void
    {
        $command = $this->command();
        $this->expectMigration($command, 1);
        $command->shouldReceive('createAdministrator')->never();
        $this->assertSame(1, $this->runCommand($command)[0]);
        $this->assertFileDoesNotExist($this->marker());
        $this->assertTrue(Schema::hasTable('settings'));
        $retry = $this->command();
        $retry->shouldReceive('call')->never();
        $this->assertSame(1, $this->runCommand($retry)[0]);
    }

    public function test_administrator_failure_leaves_initialization_incomplete(): void
    {
        $command = $this->command();
        $this->expectMigration($command);
        $command->shouldReceive('createAdministrator')->once()->andThrow(new RuntimeException('Admin creation failed.'));
        $this->assertSame(1, $this->runCommand($command)[0]);
        $this->assertFileDoesNotExist($this->marker());
    }

    public function test_search_failure_does_not_record_success(): void
    {
        $command = $this->command();
        $this->expectMigration($command);
        $this->expectAdministrator($command);
        $command->shouldReceive('call')->once()->with('manticore:create-indexes', ['--no-interaction' => true])->andReturn(1);
        $this->assertSame(1, $this->runCommand($command)[0]);
        $this->assertFileDoesNotExist($this->marker());
    }

    public function test_concurrent_initialization_is_rejected(): void
    {
        File::ensureDirectoryExists(dirname($this->marker()));
        $lock = fopen(dirname($this->marker()).'/deploy-init.lock', 'c');
        $this->assertIsResource($lock);
        flock($lock, LOCK_EX | LOCK_NB);
        try {
            $command = $this->command();
            $command->shouldReceive('call')->never();
            [$status, $output] = $this->runCommand($command);
            $this->assertSame(1, $status);
            $this->assertStringContainsString('Another initialization', $output);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function marker(): string
    {
        return $this->directory.'/_install/install.lock';
    }

    /** @return DeployInitialize&MockInterface */
    private function command(): DeployInitialize
    {
        /** @var DeployInitialize&MockInterface $command */
        $command = Mockery::mock(DeployInitialize::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $command->__construct();
        $command->setLaravel($this->app);

        return $command;
    }

    /** @param DeployInitialize&MockInterface $command */
    private function expectMigration(DeployInitialize $command, int $status = 0): void
    {
        $command->shouldReceive('call')->once()->with('migrate', ['--force' => true, '--seed' => true, '--no-interaction' => true])->andReturnUsing(function () use ($status): int {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name');
                $table->string('value');
            });
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('username');
                $table->integer('roles_id');
                $table->timestamp('email_verified_at')->nullable();
            });
            DB::table('settings')->insert(['name' => 'categorizeforeign', 'value' => '0']);

            return $status;
        });
    }

    /** @param DeployInitialize&MockInterface $command */
    private function expectAdministrator(DeployInitialize $command): void
    {
        $command->shouldReceive('createAdministrator')->once()->andReturnUsing(function (): void {
            DB::table('users')->insert(['username' => 'deployment-admin', 'roles_id' => 2, 'email_verified_at' => now()]);
        });
    }

    /** @return array{int, string} */
    private function runCommand(DeployInitialize $command): array
    {
        $output = new BufferedOutput;
        $status = $command->run(new ArrayInput([]), $output);

        return [$status, $output->fetch()];
    }
}
