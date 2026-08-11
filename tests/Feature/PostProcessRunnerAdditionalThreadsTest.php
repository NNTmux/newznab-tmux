<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\AdditionalProcessing\DTO\AdditionalBatchResult;
use App\Services\AdditionalProcessing\DTO\DownloadMetrics;
use App\Services\AdditionalProcessing\DTO\PersistenceMetrics;
use App\Services\AdditionalProcessing\DTO\ReleaseProcessingResult;
use App\Services\AdditionalProcessing\Enums\ProcessingOutcome;
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
        $this->databasePath = sys_get_temp_dir().'/nntmux-postprocess-additional-threads.sqlite';
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
                ->andReturn($this->successfulBatch(1));
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
                ->andReturn($this->successfulBatch(1));
            $mock->shouldReceive('start')
                ->once()
                ->ordered()
                ->with('', 'a', Mockery::type('string'), [1])
                ->andReturn($this->successfulBatch(2));
            $mock->shouldReceive('start')
                ->once()
                ->ordered()
                ->with('', 'a', Mockery::type('string'), [1, 2])
                ->andReturn(AdditionalBatchResult::empty());
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

    public function test_worker_guid_command_emits_machine_readable_batch_profiles(): void
    {
        $batchResult = $this->successfulBatch(1)->withPerformance(2.0, 1024);
        $this->mock(AdditionalProcessingOrchestrator::class, function (MockInterface $mock) use ($batchResult): void {
            $mock->shouldReceive('start')->once()->andReturn($batchResult);
            $mock->shouldReceive('finish')->once();
        });

        $status = Artisan::call('postprocess:guid', [
            'type' => 'additional',
            'guid' => 'a',
            '--profile' => true,
        ]);
        $profile = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $status);
        $this->assertSame('additional-postprocessing-profile', $profile['event']);
        $this->assertSame('v2', $profile['pipeline']);
        $this->assertSame(1, $profile['attempted']);
        $this->assertSame(1800, $profile['releases_per_hour']);
        $this->assertSame(1024, $profile['peak_memory_bytes']);
    }

    public function test_targeted_additional_command_reports_a_successful_typed_outcome(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert($this->releaseRow(1, 'a'));

        $this->mock(AdditionalProcessingOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('processSingleGuid')
                ->once()
                ->with('a-guid-1')
                ->andReturn(new ReleaseProcessingResult(
                    1,
                    'a-guid-1',
                    ProcessingOutcome::Completed,
                    artifactsCreated: true,
                    releaseFilesAdded: 2,
                    elapsedSeconds: 1.2345,
                    stageDurations: ['nzb-parsing' => 0.5],
                    downloadMetrics: new DownloadMetrics(2, 1, 1, 100, 100),
                    persistenceMetrics: new PersistenceMetrics(4, 3.5, 2, 1),
                ));
            $mock->shouldReceive('finish')->once();
        });

        $status = Artisan::call('releases:additional', ['--id' => 1, '--profile' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('outcome completed', $output);
        $this->assertStringContainsString('Artifacts created', $output);
        $this->assertStringContainsString('Release files added', $output);
        $this->assertStringContainsString('Stage profile', $output);
        $this->assertStringContainsString('Download profile', $output);
        $this->assertStringContainsString('Persistence profile', $output);
    }

    public function test_targeted_additional_command_fails_for_an_unsuccessful_typed_outcome(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert($this->releaseRow(1, 'a'));

        $this->mock(AdditionalProcessingOrchestrator::class, function (MockInterface $mock): void {
            $mock->shouldReceive('processSingleGuid')
                ->once()
                ->with('a-guid-1')
                ->andReturn(new ReleaseProcessingResult(
                    1,
                    'a-guid-1',
                    ProcessingOutcome::TimedOut,
                    reason: 'The release exceeded the post-processing timeout.',
                    elapsedSeconds: 2.3456,
                ));
            $mock->shouldReceive('finish')->once();
        });

        $status = Artisan::call('releases:additional', ['--id' => 1]);
        $output = Artisan::output();

        $this->assertSame(3, $status);
        $this->assertStringContainsString('outcome timed-out', $output);
        $this->assertStringContainsString('Reason', $output);
        $this->assertStringContainsString('The release exceeded the post-processing timeout.', $output);
    }

    public function test_additional_diagnostics_reports_stale_claims_and_preflight_warnings_as_json(): void
    {
        config(['nntmux.tmp_unrar_path' => '']);
        DB::table('settings')->upsert([
            ['name' => 'postthreads', 'value' => '33'],
            ['name' => 'maxaddprocessed', 'value' => '25'],
            ['name' => 'maxpptimeoutcount', 'value' => '3'],
        ], ['name'], ['value']);
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert([
            ...$this->releaseRow(1, 'a'),
            'additional_pp_claimed_at' => now()->subHour(),
            'additional_pp_claim_token' => 'stale-claim',
        ]);

        $status = Artisan::call('nntmux:additional-diagnose', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $warningCodes = array_column($report['warnings'], 'code');

        $this->assertSame(0, $status);
        $this->assertSame(1, $report['backlog']['total']);
        $this->assertSame(1, $report['backlog']['available']);
        $this->assertSame(1, $report['backlog']['stale_claims']);
        $this->assertSame(825, $report['capacity']['max_in_flight']);
        $this->assertSame('v2', $report['settings']['pipeline']);
        $this->assertContains('stale-claims', $warningCodes);
        $this->assertContains('claim-ttl', $warningCodes);
        $this->assertContains('missing-index', $warningCodes);
        $this->assertContains('unwritable-temp-path', $warningCodes);
        $this->assertContains('oversized-capacity', $warningCodes);
    }

    public function test_additional_diagnostics_recognizes_the_covering_claim_index(): void
    {
        Schema::table('releases', function (Blueprint $table): void {
            $table->index([
                'passwordstatus',
                'haspreview',
                'nzbstatus',
                'leftguid',
                'additional_pp_claimed_at',
                'postdate',
            ], 'ix_releases_add_pp_claim_queue');
        });

        $status = Artisan::call('nntmux:additional-diagnose', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $status);
        $this->assertFalse($report['indexes']['missing']);
        $this->assertSame('ix_releases_add_pp_claim_queue', $report['indexes']['covering_index']);
        $this->assertNotContains('missing-index', array_column($report['warnings'], 'code'));
    }

    private function successfulBatch(int $releaseId): AdditionalBatchResult
    {
        return new AdditionalBatchResult(
            [$releaseId],
            [new ReleaseProcessingResult($releaseId, 'a-guid-'.$releaseId, ProcessingOutcome::Completed)],
        );
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
