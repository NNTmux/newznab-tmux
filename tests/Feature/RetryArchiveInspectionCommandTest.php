<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class RetryArchiveInspectionCommandTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-retry-archive-inspection-test.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        $pdo = new PDO('sqlite:'.$this->databasePath);

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
        ]);

        DB::purge();
        DB::reconnect();

        Schema::dropIfExists('releases');
        Schema::create('releases', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
            $table->integer('passwordstatus');
            $table->integer('haspreview');
            $table->integer('nzbstatus');
            $table->timestamp('archive_retry_at')->nullable();
        });

        DB::table('releases')->insert([
            $this->releaseRow(1, '[01/10] - "G9AJxkjPdN0iBdyGdhdseeuLL2k8Bl9e.7z.001" yEnc'),
            $this->releaseRow(2, 'Some.Release.2026.part01.rar'),
            $this->releaseRow(3, 'Some.Release.2026.zip'),
            $this->releaseRow(4, 'KlUC4yTqeaIpcTbOIYdzhqqWF'),
            $this->releaseRow(5, 'Already.Retried.7z', archiveRetryAt: '2026-10-01 00:00:00'),
            $this->releaseRow(6, 'Listed.Release.rar', passwordStatus: 0),
            $this->releaseRow(7, 'Pending.Release.rar', hasPreview: -1),
            $this->releaseRow(8, 'No.Nzb.Release.rar', nzbStatus: 0),
        ]);
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

    public function test_dry_run_counts_eligible_releases_by_archive_type_without_queueing_them(): void
    {
        $this->artisan('releases:retry-archive-inspection', ['--dry-run' => true])
            ->expectsTable(['Archive type', 'Releases'], [['7z', 1], ['rar', 1], ['zip', 1], ['unknown', 1]])
            ->expectsOutput('Dry run: 4 release(s) would be queued.')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('releases')->where('haspreview', -1)->where('id', '<>', 7)->count());
        $this->assertSame(1, DB::table('releases')->whereNotNull('archive_retry_at')->count());
    }

    public function test_it_queues_each_eligible_release_once(): void
    {
        $this->freezeSecond();

        $this->artisan('releases:retry-archive-inspection', ['--limit' => 3])
            ->expectsOutput('Queued 3 release(s) for one more archive inspection.')
            ->assertSuccessful();
        $this->artisan('releases:retry-archive-inspection')
            ->expectsOutput('Queued 1 release(s) for one more archive inspection.')
            ->assertSuccessful();
        $this->artisan('releases:retry-archive-inspection')
            ->expectsOutput('Queued 0 release(s) for one more archive inspection.')
            ->assertSuccessful();

        $queued = DB::table('releases')->whereIn('id', [1, 2, 3, 4])->get();
        $this->assertSame([-1], $queued->pluck('haspreview')->map(static fn (mixed $value): int => (int) $value)->unique()->values()->all());
        $this->assertSame([now()->toDateTimeString()], $queued->pluck('archive_retry_at')->unique()->values()->all());
        $this->assertSame(0, (int) DB::table('releases')->where('id', 5)->value('haspreview'));
        $this->assertSame('2026-10-01 00:00:00', DB::table('releases')->where('id', 5)->value('archive_retry_at'));
        $this->assertSame(0, DB::table('releases')->whereIn('id', [6, 7, 8])->whereNotNull('archive_retry_at')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseRow(
        int $id,
        string $name,
        int $passwordStatus = -1,
        int $hasPreview = 0,
        int $nzbStatus = 1,
        ?string $archiveRetryAt = null,
    ): array {
        return [
            'id' => $id,
            'name' => $name,
            'passwordstatus' => $passwordStatus,
            'haspreview' => $hasPreview,
            'nzbstatus' => $nzbStatus,
            'archive_retry_at' => $archiveRetryAt,
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
}
