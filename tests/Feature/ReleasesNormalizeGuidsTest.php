<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class ReleasesNormalizeGuidsTest extends TestCase
{
    private string $databasePath = '';

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-normalize-guids.sqlite';
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

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);
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

    public function test_leftguid_is_resynced_in_place(): void
    {
        $guid = '01234567-89ab-cdef-0123-456789abcdef';
        $this->insertRelease(1, $guid, 'f');

        $status = Artisan::call('releases:normalize-guids');

        $this->assertSame(0, $status);
        $this->assertSame('0', DB::table('releases')->where('id', 1)->value('leftguid'));
        $this->assertSame($guid, DB::table('releases')->where('id', 1)->value('guid'));
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $this->insertRelease(1, '01234567-89ab-cdef-0123-456789abcdef', 'f');
        $this->insertRelease(2, 'd41d8cd98f00b204e9800998ecf8427e', 'd');

        $status = Artisan::call('releases:normalize-guids', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('Dry run: no rows are modified.', $output);
        $this->assertSame('f', DB::table('releases')->where('id', 1)->value('leftguid'));
        $this->assertSame('d41d8cd98f00b204e9800998ecf8427e', DB::table('releases')->where('id', 2)->value('guid'));
    }

    public function test_invalid_guid_is_reported_and_never_rewritten(): void
    {
        $this->insertRelease(1, 'd41d8cd98f00b204e9800998ecf8427e', 'd', ['name' => 'Legacy nZEDb release']);

        $status = Artisan::call('releases:normalize-guids');
        $output = Artisan::output();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('1 releases have a guid that blocks the normalization migration.', $output);
        $this->assertStringContainsString('d41d8cd98f00b204e9800998ecf8427e', $output);
        $this->assertStringContainsString('Legacy nZEDb release', $output);
        $this->assertSame('d41d8cd98f00b204e9800998ecf8427e', DB::table('releases')->where('id', 1)->value('guid'));
    }

    public function test_case_insensitive_duplicates_report_every_id_but_the_lowest(): void
    {
        $guid = 'abcdef01-2345-6789-abcd-ef0123456789';
        $this->insertRelease(1, $guid, 'a');
        $this->insertRelease(2, strtoupper($guid), 'A');

        $status = Artisan::call('releases:normalize-guids');
        $output = Artisan::output();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('1 releases have a guid that blocks', $output);
        $this->assertSame($guid, DB::table('releases')->where('id', 1)->value('guid'));
        $this->assertSame(strtoupper($guid), DB::table('releases')->where('id', 2)->value('guid'));
    }

    public function test_consistent_guids_report_success(): void
    {
        $this->insertRelease(1, '01234567-89ab-cdef-0123-456789abcdef', '0');

        $status = Artisan::call('releases:normalize-guids');

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Release guids are consistent.', Artisan::output());
    }

    /** @param array<string, mixed> $overrides */
    private function insertRelease(int $id, string $guid, string $leftguid, array $overrides = []): void
    {
        DB::table('releases')->insert([
            'id' => $id,
            'guid' => $guid,
            'leftguid' => $leftguid,
            'name' => 'Release '.$id,
            ...$overrides,
        ]);
    }

    private function createSchema(): void
    {
        DB::statement('DROP TABLE IF EXISTS releases');
        DB::statement('CREATE TABLE releases (
            id INTEGER PRIMARY KEY,
            guid VARCHAR(40) NOT NULL,
            leftguid CHAR(1) NOT NULL,
            name VARCHAR(255) NOT NULL DEFAULT ""
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
