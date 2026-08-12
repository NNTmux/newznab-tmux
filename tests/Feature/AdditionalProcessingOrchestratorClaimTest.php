<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Release;
use App\Services\AdditionalProcessing\AdditionalProcessingOrchestrator;
use App\Services\AdditionalProcessing\ConsoleOutputService;
use App\Services\AdditionalProcessing\ReleaseProcessor;
use App\Services\AdditionalProcessing\State\ReleaseProcessingContext;
use App\Services\TempWorkspaceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;
use Tests\Unit\AdditionalProcessing\CreatesProcessingConfiguration;

class AdditionalProcessingOrchestratorClaimTest extends TestCase
{
    use CreatesProcessingConfiguration;

    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-orchestrator-claim-test', '.sqlite');

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '0'), ('releaseprocessingtimeout', '120')");

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

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_handled_release_exception_clears_claim(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert([
            'id' => 1,
            'guid' => 'a-guid-1',
            'leftguid' => 'a',
            'name' => 'Example',
            'searchname' => 'Example',
            'size' => 2 * 1048576,
            'groups_id' => 1,
            'nfostatus' => -1,
            'fromname' => 'poster@example.test',
            'completion' => 100,
            'categories_id' => 1,
            'predb_id' => 0,
            'pp_timeout_count' => 0,
            'passwordstatus' => -1,
            'haspreview' => -1,
            'nzbstatus' => 1,
            'postdate' => '2026-07-12 10:00:00',
            'additional_pp_claimed_at' => null,
            'additional_pp_claim_token' => null,
        ]);

        $processor = new FailingAdditionalReleaseProcessor;
        $tempWorkspace = new RecordingTempWorkspaceService;
        $output = new RecordingConsoleOutputService;

        $orchestrator = new AdditionalProcessingOrchestrator(
            $this->makeConfig(['queryLimit' => 25, 'minSizeMB' => 0, 'maxSizeGB' => 100]),
            $processor,
            $tempWorkspace,
            $output
        );

        $orchestrator->start('', 'a');

        $this->assertSame(1, $processor->processCalls);
        $this->assertSame(1, $tempWorkspace->ensureMainTempPathCalls);
        $this->assertSame(1, $tempWorkspace->clearDirectoryCalls);
        $this->assertSame(1, $output->echoDescriptionCalls);
        $this->assertSame(1, $output->endOutputCalls);
        $this->assertNull(Release::query()->where('id', 1)->value('additional_pp_claimed_at'));
        $this->assertNull(Release::query()->where('id', 1)->value('additional_pp_claim_token'));
    }

    public function test_temp_setup_failure_does_not_claim_releases(): void
    {
        DB::table('categories')->insert(['id' => 1, 'disablepreview' => 0]);
        DB::table('releases')->insert([
            'id' => 1,
            'guid' => 'a-guid-1',
            'leftguid' => 'a',
            'name' => 'Example',
            'searchname' => 'Example',
            'size' => 2 * 1048576,
            'groups_id' => 1,
            'nfostatus' => -1,
            'fromname' => 'poster@example.test',
            'completion' => 100,
            'categories_id' => 1,
            'predb_id' => 0,
            'pp_timeout_count' => 0,
            'passwordstatus' => -1,
            'haspreview' => -1,
            'nzbstatus' => 1,
            'postdate' => '2026-07-12 10:00:00',
            'additional_pp_claimed_at' => null,
            'additional_pp_claim_token' => null,
        ]);

        $processor = new FailingAdditionalReleaseProcessor;
        $tempWorkspace = new FailingTempWorkspaceService;
        $output = new RecordingConsoleOutputService;

        $orchestrator = new AdditionalProcessingOrchestrator(
            $this->makeConfig(['queryLimit' => 25, 'minSizeMB' => 0, 'maxSizeGB' => 100]),
            $processor,
            $tempWorkspace,
            $output
        );

        $orchestrator->start('', 'a');

        $this->assertSame(0, $processor->processCalls);
        $this->assertSame(1, $tempWorkspace->ensureMainTempPathCalls);
        $this->assertSame(0, $output->echoDescriptionCalls);
        $this->assertSame(0, $output->endOutputCalls);
        $this->assertStringContainsString('Additional post-processing skipped', $output->warnings[0] ?? '');
        $this->assertNull(Release::query()->where('id', 1)->value('additional_pp_claimed_at'));
        $this->assertNull(Release::query()->where('id', 1)->value('additional_pp_claim_token'));
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
            $table->string('name')->default('');
            $table->string('searchname')->default('');
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('groups_id')->default(0);
            $table->integer('nfostatus')->default(0);
            $table->string('fromname')->nullable();
            $table->float('completion')->default(0);
            $table->unsignedInteger('categories_id');
            $table->unsignedInteger('predb_id')->default(0);
            $table->integer('pp_timeout_count')->default(0);
            $table->integer('passwordstatus');
            $table->integer('haspreview');
            $table->integer('nzbstatus');
            $table->dateTime('postdate')->nullable();
            $table->timestamp('additional_pp_claimed_at')->nullable();
            $table->string('additional_pp_claim_token', 64)->nullable();
        });
    }
}

class FailingAdditionalReleaseProcessor extends ReleaseProcessor
{
    public int $processCalls = 0;

    public function __construct() {}

    public function process(ReleaseProcessingContext $context, string $mainTmpPath): void
    {
        $this->processCalls++;

        throw new \RuntimeException('boom');
    }
}

class RecordingTempWorkspaceService extends TempWorkspaceService
{
    public int $ensureMainTempPathCalls = 0;

    public int $clearDirectoryCalls = 0;

    public function ensureMainTempPath(
        string $basePath,
        string $guidChar = '',
        string $groupID = '',
        string $workerToken = ''
    ): string {
        $this->ensureMainTempPathCalls++;

        // A stub value only: clearDirectory() below is a counter, so nothing is
        // ever created here and the path never reaches the filesystem.
        return sys_get_temp_dir().'/nntmux-orchestrator-claim-workspace/';
    }

    public function clearDirectory(string $path, bool $preserveRoot = true): void
    {
        $this->clearDirectoryCalls++;
    }
}

class FailingTempWorkspaceService extends RecordingTempWorkspaceService
{
    public function ensureMainTempPath(
        string $basePath,
        string $guidChar = '',
        string $groupID = '',
        string $workerToken = ''
    ): string {
        $this->ensureMainTempPathCalls++;

        throw new \RuntimeException('Additional post-processing temp path "/root/nope/a/" is not writable');
    }
}

class RecordingConsoleOutputService extends ConsoleOutputService
{
    public int $echoDescriptionCalls = 0;

    public int $endOutputCalls = 0;

    /**
     * @var list<string>
     */
    public array $warnings = [];

    public function echoDescription(int $totalReleases): void
    {
        $this->echoDescriptionCalls++;
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function endOutput(): void
    {
        $this->endOutputCalls++;
    }
}
