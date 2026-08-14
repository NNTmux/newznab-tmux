<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class ReleasesOptimizePreflightTest extends TestCase
{
    private string $databasePath = '';

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-releases-preflight.sqlite';
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings VALUES ('categorizeforeign', '0'), ('catwebdl', '0')");
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath]);
        DB::purge();
        DB::reconnect();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        parent::tearDown();
    }

    public function test_valid_data_reports_storage_and_every_migration_data_category(): void
    {
        $this->insertRelease(1, '01234567-89ab-cdef-0123-456789abcdef', '0', [
            'nzb_password' => 'secret',
            'nzb_creation_attempts' => 1,
            'nzb_creation_last_error' => 'temporary',
            'source' => 2,
        ]);
        DB::table('release_comments')->insert([
            ['id' => 1, 'releases_id' => 1, 'isvisible' => 1, 'gid' => 'legacy'],
            ['id' => 2, 'releases_id' => 1, 'isvisible' => 0, 'gid' => null],
        ]);

        $status = Artisan::call('releases:optimize-preflight', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $status);
        $this->assertTrue($report['ok']);
        $this->assertSame(1, $report['migration_data']['nzb_password_rows']);
        $this->assertSame(1, $report['migration_data']['nzb_creation_failure_rows']);
        $this->assertSame(1, $report['migration_data']['visible_comment_rows']);
        $this->assertSame(1, $report['migration_data']['release_comment_counter_mismatches']);
        $this->assertSame(1, $report['discarded_data']['releases.source']);
        $this->assertSame(1, $report['discarded_data']['release_comments.gid']);
        $this->assertNull($report['storage']['total_bytes']);
    }

    public function test_every_identifier_blocker_fails_preflight(): void
    {
        $scenarios = [
            'duplicate_ids' => function (): void {
                $this->insertRelease(1, '01234567-89ab-cdef-0123-456789abcdef', '0');
                $this->insertRelease(1, '11234567-89ab-cdef-0123-456789abcdef', '1');
            },
            'invalid_guids' => fn () => $this->insertRelease(1, 'not-a-guid', 'n'),
            'case_insensitive_duplicate_guids' => function (): void {
                $this->insertRelease(1, 'abcdef01-2345-6789-abcd-ef0123456789', 'a');
                $this->insertRelease(2, 'ABCDEF01-2345-6789-ABCD-EF0123456789', 'A');
            },
            'leftguid_mismatches' => fn () => $this->insertRelease(1, '01234567-89ab-cdef-0123-456789abcdef', 'f'),
        ];

        foreach ($scenarios as $expectedBlocker => $seed) {
            DB::table('release_comments')->delete();
            DB::table('releases')->delete();
            $seed();

            $status = Artisan::call('releases:optimize-preflight', ['--json' => true]);
            $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame(1, $status, $expectedBlocker);
            $this->assertContains($expectedBlocker, array_column($report['blockers'], 'code'));
        }
    }

    public function test_human_report_includes_storage_discarded_data_and_index_sections(): void
    {
        $this->insertRelease(1, '01234567-89ab-cdef-0123-456789abcdef', '0', ['source' => 2]);

        $status = Artisan::call('releases:optimize-preflight');
        $output = Artisan::output();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Storage: not reported for the sqlite driver.', $output);
        $this->assertStringContainsString('Dropped column', $output);
        $this->assertStringContainsString('releases.source', $output);
        $this->assertStringContainsString('permanently destroyed', $output);
        $this->assertStringContainsString('Index change', $output);
        $this->assertStringContainsString('ux_releases_guid', $output);
    }

    /** @param array<string, mixed> $overrides */
    private function insertRelease(int $id, string $guid, string $leftguid, array $overrides = []): void
    {
        DB::table('releases')->insert([
            'id' => $id,
            'guid' => $guid,
            'leftguid' => $leftguid,
            'comments' => 0,
            'nzb_password' => null,
            'nzb_creation_attempts' => 0,
            'nzb_creation_last_error' => null,
            'updatetime' => now(),
            'gid' => null,
            'source' => null,
            'proc_sorter' => 0,
            'audiostatus' => 0,
            ...$overrides,
        ]);
    }

    private function createSchema(): void
    {
        DB::statement('DROP TABLE IF EXISTS release_comments');
        DB::statement('DROP TABLE IF EXISTS releases');
        DB::statement('CREATE TABLE releases (
            id INTEGER NOT NULL,
            guid VARCHAR(40), leftguid CHAR(1), comments INTEGER DEFAULT 0,
            nzb_password VARCHAR(255), nzb_creation_attempts INTEGER DEFAULT 0,
            nzb_creation_last_error TEXT, updatetime DATETIME, gid VARCHAR(32),
            source INTEGER, proc_sorter INTEGER DEFAULT 0, audiostatus INTEGER DEFAULT 0
        )');
        DB::statement('CREATE TABLE release_comments (
            id INTEGER PRIMARY KEY, releases_id INTEGER NOT NULL, isvisible INTEGER DEFAULT 1,
            gid VARCHAR(32), cid VARCHAR(32), issynced INTEGER DEFAULT 0, shared INTEGER DEFAULT 0,
            shareid VARCHAR(40) DEFAULT \'\', siteid VARCHAR(40) DEFAULT \'\', sourceid INTEGER
        )');
    }

    private function setEnvironmentValue(string $key, ?string $value): void
    {
        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
}
