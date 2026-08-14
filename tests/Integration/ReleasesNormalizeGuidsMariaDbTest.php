<?php

declare(strict_types=1);

namespace Tests\Integration;

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the MySQL-only branches of `releases:normalize-guids`: the `NOT REGEXP`
 * validity check and the duplicate-guid sample join. The SQLite feature test
 * exercises the portable branches instead.
 */
final class ReleasesNormalizeGuidsMariaDbTest extends TestCase
{
    private string $tablePrefix;

    public function createApplication()
    {
        Dotenv::createMutable(dirname(__DIR__, 2))->safeLoad();
        $this->tablePrefix = 'guid_norm_'.getmypid().'_'.bin2hex(random_bytes(4)).'_';
        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_URL', null);
        $this->setEnvironmentValue('DB_CONNECTION', 'mariadb');
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

        DB::statement('DROP TABLE IF EXISTS `'.$this->table('releases').'`');
        DB::statement(
            'CREATE TABLE `'.$this->table('releases').'` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `guid` VARCHAR(64) NOT NULL,
                `leftguid` CHAR(1) NOT NULL DEFAULT "",
                `name` VARCHAR(255) NOT NULL DEFAULT "",
                PRIMARY KEY (`id`),
                KEY `ix_releases_guid` (`guid`)
            ) ENGINE=InnoDB'
        );
    }

    protected function tearDown(): void
    {
        if (isset($this->tablePrefix) && preg_match('/^guid_norm_\d+_[a-f0-9]{8}_$/', $this->tablePrefix) === 1) {
            DB::statement('DROP TABLE IF EXISTS `'.$this->table('releases').'`');
        }
        DB::disconnect();
        parent::tearDown();
    }

    #[Test]
    public function clean_data_passes_and_resyncs_leftguid(): void
    {
        $this->insert(1, '01234567-89ab-cdef-0123-456789abcdef', 'z');

        $status = Artisan::call('releases:normalize-guids');

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Release guids are consistent.', Artisan::output());
        $this->assertSame('0', DB::table('releases')->where('id', 1)->value('leftguid'));
    }

    #[Test]
    public function legacy_sha1_guids_fit_the_narrowed_column_and_do_not_block(): void
    {
        $this->insert(1, '01234567-89ab-cdef-0123-456789abcdef', '0');
        $this->insert(2, '0c5c002220e26542a4c9dae845a58a38b3e7e63a', '0', 'Legacy nZEDb release');

        $status = Artisan::call('releases:normalize-guids');
        $output = Artisan::output();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Release guids are consistent.', $output);
        $this->assertStringContainsString('1 releases still carry a legacy non-UUID guid', $output);
        $this->assertSame('0c5c002220e26542a4c9dae845a58a38b3e7e63a', DB::table('releases')->where('id', 2)->value('guid'));
    }

    #[Test]
    public function oversized_guids_are_detected_by_the_regexp_check(): void
    {
        $this->insert(1, '01234567-89ab-cdef-0123-456789abcdef', '0');
        $this->insert(2, str_repeat('a', 41), 'a', 'Oversized guid release');

        $status = Artisan::call('releases:normalize-guids');
        $output = Artisan::output();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('1 releases have a guid that blocks', $output);
        $this->assertStringContainsString('Oversized guid release', $output);
        $this->assertSame(str_repeat('a', 41), DB::table('releases')->where('id', 2)->value('guid'));
    }

    #[Test]
    public function case_insensitive_duplicates_are_counted_and_sampled(): void
    {
        $guid = 'abcdef01-2345-6789-abcd-ef0123456789';
        $this->insert(1, $guid, 'a');
        $this->insert(2, strtoupper($guid), 'A', 'Duplicate of one');
        $this->insert(3, strtoupper($guid), 'A', 'Second duplicate');

        $status = Artisan::call('releases:normalize-guids');
        $output = Artisan::output();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('2 releases have a guid that blocks', $output);
        $this->assertStringContainsString('Duplicate of one', $output);
        $this->assertStringContainsString('Second duplicate', $output);
        $this->assertSame(3, DB::table('releases')->count());
    }

    #[Test]
    public function scans_never_use_offset_paging(): void
    {
        for ($id = 1; $id <= 250; $id++) {
            $this->insert($id, sprintf('%08x-89ab-cdef-0123-456789abcdef', $id), 'x');
        }

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        Artisan::call('releases:normalize-guids', ['--chunk' => 100]);

        $this->assertNotEmpty($queries);
        foreach ($queries as $sql) {
            $this->assertStringNotContainsStringIgnoringCase(
                'offset',
                $sql,
                'releases:normalize-guids must page by primary key, never with LIMIT/OFFSET.',
            );
        }
    }

    private function insert(int $id, string $guid, string $leftguid, string $name = 'Release'): void
    {
        DB::table('releases')->insert([
            'id' => $id,
            'guid' => $guid,
            'leftguid' => $leftguid,
            'name' => $name,
        ]);
    }

    private function table(string $name): string
    {
        return $this->tablePrefix.$name;
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
