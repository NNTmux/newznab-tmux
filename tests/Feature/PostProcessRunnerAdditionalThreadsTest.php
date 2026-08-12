<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\Runners\PostProcessRunner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Mockery\MockInterface;
use PDO;
use Tests\TestCase;

class PostProcessRunnerAdditionalThreadsTest extends TestCase
{
    private string $databasePath;

    private string $tmpUnrarPath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-postprocess-additional-threads', '.sqlite');
        $this->tmpUnrarPath = sys_get_temp_dir().'/nntmux-additional-threads-'.uniqid('', true);

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '0'), ('postthreads', '5'), ('releaseprocessingtimeout', '120')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'nntmux.echocli' => false,
            'nntmux.tmp_unrar_path' => $this->tmpUnrarPath,
            'nntmux_settings.check_passworded_rars' => true,
            'nntmux_settings.unrar_path' => '/usr/bin/unrar',
        ]);

        DB::purge();
        DB::reconnect();

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        if ($this->tmpUnrarPath !== '' && is_dir($this->tmpUnrarPath)) {
            app('files')->deleteDirectory($this->tmpUnrarPath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_process_additional_uses_configured_postthreads_for_streaming_process_pool(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        foreach (['0', '1', '2', '3', '4'] as $index => $leftguid) {
            DB::table('releases')->insert($this->releaseRow($index + 1, $leftguid));
        }

        $runner = new class extends PostProcessRunner
        {
            public int $capturedMaxProcesses = 0;

            /**
             * @var array<string|int, string>
             */
            public array $capturedCommands = [];

            protected function runStreamingCommands(array $commands, int $maxProcesses, string $desc): void
            {
                $this->capturedCommands = $commands;
                $this->capturedMaxProcesses = $maxProcesses;
            }
        };

        $runner->processAdditional();

        $this->assertSame(5, $runner->capturedMaxProcesses);
        $this->assertCount(5, $runner->capturedCommands);
        $this->assertContains(PHP_BINARY.' artisan postprocess:guid additional 0 --worker --max-batches=4', $runner->capturedCommands);
        $this->assertContains(PHP_BINARY.' artisan postprocess:guid additional 4 --worker --max-batches=4', $runner->capturedCommands);
    }

    public function test_process_additional_repeats_hot_bucket_to_fill_configured_threads(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        foreach (range(1, 125) as $id) {
            DB::table('releases')->insert($this->releaseRow($id, 'a'));
        }

        $runner = new class extends PostProcessRunner
        {
            public int $capturedMaxProcesses = 0;

            /**
             * @var array<string|int, string>
             */
            public array $capturedCommands = [];

            protected function runStreamingCommands(array $commands, int $maxProcesses, string $desc): void
            {
                $this->capturedCommands = $commands;
                $this->capturedMaxProcesses = $maxProcesses;
            }
        };

        $runner->processAdditional();

        $this->assertSame(5, $runner->capturedMaxProcesses);
        $this->assertCount(5, $runner->capturedCommands);
        $this->assertSame(
            array_fill(0, 5, PHP_BINARY.' artisan postprocess:guid additional a --worker --max-batches=4'),
            array_values($runner->capturedCommands)
        );
    }

    public function test_process_additional_does_not_start_idle_workers_for_a_small_hot_bucket(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        foreach (range(1, 5) as $id) {
            DB::table('releases')->insert($this->releaseRow($id, 'a'));
        }

        $runner = new class extends PostProcessRunner
        {
            /**
             * @var array<string|int, string>
             */
            public array $capturedCommands = [];

            protected function runStreamingCommands(array $commands, int $maxProcesses, string $desc): void
            {
                $this->capturedCommands = $commands;
            }
        };

        $runner->processAdditional();

        $this->assertSame([
            PHP_BINARY.' artisan postprocess:guid additional a --worker --max-batches=4',
        ], array_values($runner->capturedCommands));
    }

    public function test_direct_guid_command_processes_one_batch(): void
    {
        $this->mock(AdditionalProcessingOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('start')
                ->once()
                ->with('', 'a', Mockery::type('string'), [])
                ->andReturn(['claimed' => 1, 'processed' => 1, 'failed' => 0, 'claimed_ids' => [1]]);
            $mock->shouldReceive('finish')->once();
        });

        $status = Artisan::call('postprocess:guid', [
            'type' => 'additional',
            'guid' => 'a',
        ]);

        $this->assertSame(0, $status);
    }

    public function test_worker_guid_command_processes_bounded_distinct_batches(): void
    {
        $this->mock(AdditionalProcessingOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('start')
                ->once()
                ->ordered()
                ->with('', 'a', Mockery::type('string'), [])
                ->andReturn(['claimed' => 1, 'processed' => 1, 'failed' => 0, 'claimed_ids' => [1]]);
            $mock->shouldReceive('start')
                ->once()
                ->ordered()
                ->with('', 'a', Mockery::type('string'), [1])
                ->andReturn(['claimed' => 1, 'processed' => 1, 'failed' => 0, 'claimed_ids' => [2]]);
            $mock->shouldReceive('start')
                ->once()
                ->ordered()
                ->with('', 'a', Mockery::type('string'), [1, 2])
                ->andReturn(['claimed' => 0, 'processed' => 0, 'failed' => 0, 'claimed_ids' => []]);
            $mock->shouldReceive('finish')->once();
        });

        $status = Artisan::call('postprocess:guid', [
            'type' => 'additional',
            'guid' => 'a',
            '--worker' => true,
            '--max-batches' => 4,
        ]);

        $this->assertSame(0, $status);
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseRow(int $id, string $leftguid): array
    {
        return [
            'id' => $id,
            'guid' => $leftguid.'-guid-'.$id,
            'leftguid' => $leftguid,
            'passwordstatus' => -1,
            'haspreview' => -1,
            'nzbstatus' => 1,
            'categories_id' => 1,
            'size' => 2 * 1048576,
            'postdate' => '2026-07-12 10:00:00',
            'additional_pp_claimed_at' => null,
            'additional_pp_claim_token' => null,
        ];
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        DB::table('settings')->upsert([
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'postthreads', 'value' => '5'],
            ['name' => 'releaseprocessingtimeout', 'value' => '120'],
        ], ['name'], ['value']);

        Schema::dropIfExists('releases');
        Schema::dropIfExists('categories');

        Schema::create('categories', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->boolean('disablepreview')->default(false);
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('guid');
            $table->char('leftguid', 1);
            $table->integer('passwordstatus');
            $table->integer('haspreview');
            $table->integer('nzbstatus');
            $table->unsignedInteger('categories_id');
            $table->unsignedBigInteger('size');
            $table->dateTime('postdate')->nullable();
            $table->timestamp('additional_pp_claimed_at')->nullable();
            $table->string('additional_pp_claim_token', 64)->nullable();
        });
    }
}
