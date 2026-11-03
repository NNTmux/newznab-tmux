<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\RenameApplyResult;
use App\Services\AdditionalProcessing\ReleaseSearchSyncCoordinator;
use App\Services\AdditionalProcessing\State\PersistenceMetricsCollector;
use App\Services\Categorization\CategorizationService;
use App\Services\NameFixing\ReleaseUpdateService;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Collation and row-lock behaviour of guarded renames on the supported engine.
 * SQLite compares and locks differently, so these properties are only proven here.
 */
final class ReleaseUpdateGuardMariaDbTest extends TestCase
{
    private string $tablePrefix;

    public function createApplication()
    {
        Dotenv::createMutable(dirname(__DIR__, 2))->safeLoad();

        $this->tablePrefix = 'renameguard_'.getmypid().'_'.bin2hex(random_bytes(4)).'_';
        $database = (string) ($_ENV['DB_DATABASE'] ?? 'nntmux');

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_URL', null);
        $this->setEnvironmentValue('DB_CONNECTION', 'mariadb');
        $this->setEnvironmentValue('DB_DATABASE', $database);

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        $app->make('config')->set('database.connections.mariadb.prefix', $this->tablePrefix);
        $app->make('db')->purge('mariadb');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'mariadb') {
            $this->markTestSkipped('MariaDB integration test.');
        }

        config(['nntmux.echocli' => false]);
        Event::fake();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->tablePrefix) && preg_match('/^renameguard_\d+_[a-f0-9]{8}_$/', $this->tablePrefix) === 1) {
            DB::statement('DROP TABLE IF EXISTS `'.$this->tablePrefix.'releases`');
            DB::statement('DROP TABLE IF EXISTS `'.$this->tablePrefix.'predb`');
        }

        DB::disconnect();
        parent::tearDown();
    }

    #[Test]
    public function collation_equal_but_byte_different_names_are_stale(): void
    {
        foreach (['Obfuscated.Name' => 'OBFUSCATED.NAME', 'Café.Name' => 'Cafe.Name'] as $stored => $captured) {
            DB::table('releases')->delete();
            DB::table('releases')->insert($this->row(['searchname' => $stored]));

            $this->assertSame(1, DB::table('releases')->where('searchname', $captured)->count(), 'The collation treats both literals as equal.');

            $service = $this->service();
            $service->updateRelease((object) $this->row(['searchname' => $captured]), 'Canonical.Title-GROUP', 'test', true, 'PAR2, ', true, false);

            $this->assertSame(RenameApplyResult::StaleIdentity, $service->lastResult);
            $this->assertSame($stored, DB::table('releases')->value('searchname'));
        }
    }

    #[Test]
    public function a_collation_equal_predb_title_is_not_the_canonical_record(): void
    {
        DB::table('predb')->insert(['id' => 7, 'title' => 'canonical.title-group']);
        DB::table('releases')->insert($this->row());

        $service = $this->service();
        $service->updateRelease((object) $this->row(), 'Canonical.Title-GROUP', 'preDB: Match', true, 'PreDB FT Exact, ', true, false, 7, canonicalTitle: 'Canonical.Title-GROUP');

        $this->assertSame(RenameApplyResult::PredbInvalid, $service->lastResult);
        $this->assertSame(0, (int) DB::table('releases')->value('predb_id'));
    }

    #[Test]
    public function the_write_waits_for_a_competing_row_lock(): void
    {
        DB::table('releases')->insert($this->row());
        config(['database.connections.mariadb_second' => config('database.connections.mariadb')]);
        $second = DB::connection('mariadb_second');
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

        $second->beginTransaction();
        try {
            $second->table('releases')->where('id', 1)->lockForUpdate()->first();

            try {
                $this->service()->updateRelease((object) $this->row(), 'Canonical.Title-GROUP', 'test', true, 'PAR2, ', true, false);
                $this->fail('The guarded write should wait for the row lock.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('Lock wait timeout', $e->getMessage());
            }

            $second->table('releases')->where('id', 1)->update(['searchname' => 'Concurrent.Rename-OTHER']);
            $second->commit();
        } finally {
            if ($second->transactionLevel() > 0) {
                $second->rollBack();
            }
            DB::purge('mariadb_second');
        }

        $service = $this->service();
        $service->updateRelease((object) $this->row(), 'Canonical.Title-GROUP', 'test', true, 'PAR2, ', true, false);

        $this->assertSame(RenameApplyResult::StaleIdentity, $service->lastResult);
        $this->assertSame('Concurrent.Rename-OTHER', DB::table('releases')->value('searchname'));
    }

    private function service(): ReleaseUpdateService
    {
        $categorization = $this->createStub(CategorizationService::class);
        $categorization->method('determineCategory')->willReturn(['categories_id' => 10]);

        return new ReleaseUpdateService(
            $categorization,
            null,
            new ReleaseSearchSyncCoordinator(new PersistenceMetricsCollector, static function (int $releaseId): void {}, false),
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function row(array $overrides = []): array
    {
        return [
            'id' => 1,
            'name' => 'd41d8cd98f00b204e9800998ecf8427e',
            'searchname' => 'd41d8cd98f00b204e9800998ecf8427e',
            'guid' => str_repeat('a', 40),
            'groups_id' => 1,
            'categories_id' => 10,
            'predb_id' => 0,
            ...$overrides,
        ];
    }

    private function createSchema(): void
    {
        $releases = $this->tablePrefix.'releases';
        $predb = $this->tablePrefix.'predb';

        DB::statement(<<<SQL
            CREATE TABLE `{$releases}` (
                id INT UNSIGNED PRIMARY KEY,
                name VARCHAR(255) NOT NULL DEFAULT '',
                searchname VARCHAR(255) NOT NULL DEFAULT '',
                guid CHAR(40) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
                groups_id INT UNSIGNED NOT NULL DEFAULT 0,
                fromname VARCHAR(255) NULL,
                categories_id INT NOT NULL DEFAULT 10,
                videos_id INT UNSIGNED NOT NULL DEFAULT 0,
                tv_episodes_id INT NOT NULL DEFAULT 0,
                tv_lookup_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                tv_lookup_retry_at DATETIME NULL,
                imdbid VARCHAR(15) NULL,
                musicinfo_id VARCHAR(15) NULL,
                consoleinfo_id VARCHAR(15) NULL,
                bookinfo_id VARCHAR(15) NULL,
                book_lookup_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                book_lookup_retry_at DATETIME NULL,
                book_name_normalized_at DATETIME NULL,
                anidbid VARCHAR(15) NULL,
                predb_id INT UNSIGNED NOT NULL DEFAULT 0,
                iscategorized TINYINT NOT NULL DEFAULT 0,
                isrenamed TINYINT NOT NULL DEFAULT 0,
                proc_par2 TINYINT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
        DB::statement(<<<SQL
            CREATE TABLE `{$predb}` (
                id INT UNSIGNED PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                UNIQUE KEY ix_predb_title (title)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL);
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
