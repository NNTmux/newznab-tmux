<?php

declare(strict_types=1);

namespace Tests\Integration;

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReleaseSchemaOptimizationMigrationMariaDbTest extends TestCase
{
    private string $tablePrefix;

    public function createApplication()
    {
        Dotenv::createMutable(dirname(__DIR__, 2))->safeLoad();
        $this->tablePrefix = 'release_opt_'.getmypid().'_'.bin2hex(random_bytes(4)).'_';
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
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->tablePrefix) && preg_match('/^release_opt_\d+_[a-f0-9]{8}_$/', $this->tablePrefix) === 1) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach (['release_files', 'release_comments', 'release_nzb_creation_failures', 'release_nzb_passwords', 'releases'] as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$this->table($table).'`');
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        DB::disconnect();
        parent::tearDown();
    }

    #[Test]
    public function migration_up_and_down_preserve_sparse_state_and_incoming_foreign_keys(): void
    {
        $this->seedLegacyData();
        $migration = $this->migration();

        $migration->up();

        $this->assertTrue(Schema::hasTable('release_nzb_passwords'));
        $this->assertTrue(Schema::hasTable('release_nzb_creation_failures'));
        $this->assertSame('secret-one', DB::table('release_nzb_passwords')->where('releases_id', 1)->value('password'));
        $this->assertSame(2, (int) DB::table('release_nzb_creation_failures')->where('releases_id', 1)->value('attempts'));
        $this->assertSame('temporary', DB::table('release_nzb_creation_failures')->where('releases_id', 1)->value('last_error'));
        $this->assertSame(['id'], $this->primaryKeyColumns());
        $this->assertSame('ascii_general_ci', $this->columnCollation('guid'));
        $this->assertSame('char(36)', strtolower($this->columnType('guid')));
        $this->assertSame('ascii_general_ci', $this->columnCollation('leftguid'));
        $this->assertSame(1, (int) DB::table('releases')->where('id', 1)->value('comments'));
        $this->assertFalse(Schema::hasColumn('releases', 'nzb_password'));
        $this->assertFalse(Schema::hasColumn('releases', 'proc_sorter'));
        $this->assertFalse(Schema::hasColumn('release_comments', 'gid'));
        $this->assertSame(
            ['passwordstatus', 'haspreview', 'nzbstatus', 'leftguid', 'postdate', 'id', 'additional_pp_claimed_at'],
            $this->indexColumns('ix_releases_add_pp_claim_queue'),
        );
        $this->assertSame(
            ['nzbstatus', 'groups_id', 'postdate', 'id', 'nzb_creation_claimed_at'],
            $this->indexColumns('ix_releases_nzb_creation_group_queue'),
        );
        $this->assertSame(
            ['nzbstatus', 'postdate', 'id', 'nzb_creation_claimed_at'],
            $this->indexColumns('ix_releases_nzb_creation_global_queue'),
        );

        try {
            DB::table('releases')->insert($this->releaseRow(3, strtoupper('01234567-89ab-cdef-0123-456789abcdef')));
            $this->fail('ASCII case-insensitive GUID uniqueness was not enforced.');
        } catch (QueryException) {
            $this->assertSame(2, DB::table('releases')->count());
        }

        DB::table('releases')->where('id', 2)->delete();
        $this->assertDatabaseMissing('release_nzb_passwords', ['releases_id' => 2]);
        DB::table('release_files')->insert(['releases_id' => 1, 'name' => 'kept.nzb']);

        $migration->down();

        $this->assertSame(['id', 'categories_id'], $this->primaryKeyColumns());
        $this->assertTrue(Schema::hasColumn('releases', 'nzb_password'));
        $this->assertTrue(Schema::hasColumn('releases', 'nzb_creation_attempts'));
        $this->assertTrue(Schema::hasColumn('release_comments', 'gid'));
        $this->assertSame('secret-one', DB::table('releases')->where('id', 1)->value('nzb_password'));
        $this->assertSame(2, (int) DB::table('releases')->where('id', 1)->value('nzb_creation_attempts'));
        $this->assertSame('temporary', DB::table('releases')->where('id', 1)->value('nzb_creation_last_error'));
        $this->assertFalse(Schema::hasTable('release_nzb_passwords'));
        $this->assertFalse(Schema::hasTable('release_nzb_creation_failures'));
        $this->assertDatabaseHas('release_files', ['releases_id' => 1, 'name' => 'kept.nzb']);
    }

    #[Test]
    public function claim_token_and_pp_index_follow_up_narrows_columns_and_adds_size(): void
    {
        $this->seedLegacyData();
        $this->migration()->up();
        $followUp = $this->followUpMigration();

        $followUp->up();

        $this->assertSame(
            ['passwordstatus', 'haspreview', 'nzbstatus', 'leftguid', 'postdate', 'id', 'additional_pp_claimed_at', 'size'],
            $this->indexColumns('ix_releases_add_pp_claim_queue'),
        );
        foreach (['nzb_creation_claim_token', 'additional_pp_claim_token'] as $column) {
            $this->assertSame('char(32)', strtolower($this->columnType($column)));
            $this->assertSame('ascii_general_ci', $this->columnCollation($column));
        }

        $token = bin2hex(random_bytes(16));
        DB::table('releases')->where('id', 1)->update(['additional_pp_claim_token' => $token]);
        $this->assertSame($token, DB::table('releases')->where('id', 1)->value('additional_pp_claim_token'));

        $followUp->down();

        $this->assertSame(
            ['passwordstatus', 'haspreview', 'nzbstatus', 'leftguid', 'postdate', 'id', 'additional_pp_claimed_at'],
            $this->indexColumns('ix_releases_add_pp_claim_queue'),
        );
        $this->assertSame('varchar(64)', strtolower($this->columnType('additional_pp_claim_token')));
    }

    private function createSchema(): void
    {
        $releases = $this->table('releases');
        $comments = $this->table('release_comments');
        $files = $this->table('release_files');
        DB::statement(<<<SQL
            CREATE TABLE `{$releases}` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT, categories_id INT NOT NULL DEFAULT 10,
                name VARCHAR(255) NOT NULL DEFAULT '', searchname VARCHAR(255) NOT NULL DEFAULT '',
                groups_id INT UNSIGNED NOT NULL DEFAULT 0, size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                postdate DATETIME NULL, adddate DATETIME NULL, updatetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                gid VARCHAR(32) NULL, guid VARCHAR(40) NOT NULL, leftguid CHAR(1) NOT NULL,
                videos_id INT UNSIGNED NOT NULL DEFAULT 0, tv_episodes_id INT NOT NULL DEFAULT 0,
                imdbid VARCHAR(100) NULL, movieinfo_id INT NULL, predb_id INT UNSIGNED NOT NULL DEFAULT 0,
                comments INT NOT NULL DEFAULT 0, passwordstatus SMALLINT NOT NULL DEFAULT -1,
                haspreview TINYINT NOT NULL DEFAULT 0, nzbstatus TINYINT NOT NULL DEFAULT 0,
                source SMALLINT UNSIGNED NULL, nzb_password VARCHAR(255) NULL,
                proc_pp TINYINT NOT NULL DEFAULT 0, proc_sorter TINYINT NOT NULL DEFAULT 0,
                videostatus TINYINT NOT NULL DEFAULT 0, audiostatus TINYINT NOT NULL DEFAULT 0,
                additional_pp_claimed_at TIMESTAMP NULL, additional_pp_claim_token VARCHAR(64) NULL,
                nzb_creation_claimed_at TIMESTAMP NULL, nzb_creation_claim_token VARCHAR(64) NULL,
                nzb_creation_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0, nzb_creation_last_error TEXT NULL,
                PRIMARY KEY (id, categories_id),
                KEY ix_releases_guid (guid), KEY ix_releases_adddate_only (adddate),
                KEY ix_releases_videos_id (videos_id), KEY ix_releases_movieinfo_id (movieinfo_id),
                KEY ix_releases_imdbid (imdbid),
                KEY ix_releases_tv_search_covering (passwordstatus, categories_id, postdate DESC, videos_id, tv_episodes_id, groups_id),
                KEY ix_releases_passwordstatus (passwordstatus),
                KEY ix_releases_haspreview_passwordstatus (haspreview, passwordstatus),
                KEY ix_releases_postdate_searchname (postdate, searchname),
                KEY ix_releases_predb_id_searchname (predb_id, searchname),
                KEY ix_releases_size_cat (size, categories_id, passwordstatus),
                KEY ix_releases_add_pp_claim_queue (passwordstatus, haspreview, nzbstatus, leftguid, additional_pp_claimed_at, postdate DESC),
                KEY ix_releases_nzb_creation_queue (nzbstatus, groups_id, leftguid, nzb_creation_claimed_at, postdate DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci
            SQL);
        DB::statement(<<<SQL
            CREATE TABLE `{$comments}` (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT, releases_id INT UNSIGNED NOT NULL,
                text VARCHAR(2000) NOT NULL DEFAULT '', isvisible TINYINT NOT NULL DEFAULT 1,
                issynced TINYINT NOT NULL DEFAULT 0, gid VARCHAR(32), cid VARCHAR(32),
                shared TINYINT NOT NULL DEFAULT 0, shareid VARCHAR(40) NOT NULL DEFAULT '',
                siteid VARCHAR(40) NOT NULL DEFAULT '', sourceid BIGINT UNSIGNED NULL,
                PRIMARY KEY (id), CONSTRAINT fk_opt_comments FOREIGN KEY (releases_id)
                REFERENCES `{$releases}` (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB
            SQL);
        DB::statement(<<<SQL
            CREATE TABLE `{$files}` (
                releases_id INT UNSIGNED NOT NULL, name VARCHAR(255) NOT NULL,
                PRIMARY KEY (releases_id, name), CONSTRAINT fk_opt_files FOREIGN KEY (releases_id)
                REFERENCES `{$releases}` (id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB
            SQL);
    }

    private function seedLegacyData(): void
    {
        DB::table('releases')->insert([
            $this->releaseRow(1, '01234567-89ab-cdef-0123-456789abcdef', [
                'nzb_password' => 'secret-one',
                'nzb_creation_attempts' => 2,
                'nzb_creation_last_error' => 'temporary',
                'comments' => 99,
            ]),
            $this->releaseRow(2, 'abcdef01-2345-6789-abcd-ef0123456789', ['nzb_password' => 'secret-two']),
        ]);
        DB::table('release_comments')->insert([
            ['releases_id' => 1, 'text' => 'visible', 'isvisible' => 1],
            ['releases_id' => 1, 'text' => 'hidden', 'isvisible' => 0],
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function releaseRow(int $id, string $guid, array $overrides = []): array
    {
        return [
            'id' => $id,
            'categories_id' => 10,
            'name' => 'Release '.$id,
            'searchname' => 'Release '.$id,
            'groups_id' => 1,
            'size' => 1000,
            'postdate' => '2026-08-13 00:00:00',
            'adddate' => '2026-08-13 00:00:00',
            'guid' => $guid,
            'leftguid' => strtolower($guid[0]),
            'comments' => 0,
            'nzb_password' => null,
            'nzb_creation_attempts' => 0,
            'nzb_creation_last_error' => null,
            ...$overrides,
        ];
    }

    /** @return list<string> */
    private function primaryKeyColumns(): array
    {
        return array_map(
            static fn (object $row): string => (string) $row->COLUMN_NAME,
            DB::select(
                'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = \'PRIMARY\'
                 ORDER BY ORDINAL_POSITION',
                [$this->table('releases')],
            ),
        );
    }

    /** @return list<string> */
    private function indexColumns(string $index): array
    {
        $rows = DB::select('SHOW INDEX FROM `'.$this->table('releases').'` WHERE Key_name = ?', [$index]);
        usort($rows, static fn (object $left, object $right): int => (int) $left->Seq_in_index <=> (int) $right->Seq_in_index);

        return array_map(
            static fn (object $row): string => (string) $row->Column_name,
            $rows,
        );
    }

    private function columnCollation(string $column): string
    {
        return (string) DB::selectOne(
            'SELECT COLLATION_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->table('releases'), $column],
        )->COLLATION_NAME;
    }

    private function columnType(string $column): string
    {
        return (string) DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$this->table('releases'), $column],
        )->COLUMN_TYPE;
    }

    private function migration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_08_13_001652_normalize_and_optimize_releases_table.php');

        return $migration;
    }

    private function followUpMigration(): Migration
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_08_14_121946_optimize_releases_claim_tokens_and_pp_index.php');

        return $migration;
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
