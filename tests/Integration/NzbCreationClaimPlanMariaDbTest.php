<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\Nzb\NzbCreationCandidateQuery;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NzbCreationClaimPlanMariaDbTest extends TestCase
{
    private string $tablePrefix;

    public function createApplication()
    {
        Dotenv::createMutable(dirname(__DIR__, 2))->safeLoad();
        $this->tablePrefix = 'nzb_claim_'.getmypid().'_'.bin2hex(random_bytes(4)).'_';
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
        if (isset($this->tablePrefix) && preg_match('/^nzb_claim_\d+_[a-f0-9]{8}_$/', $this->tablePrefix) === 1) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach (['release_nzb_creation_failures', 'releases', 'categories', 'root_categories', 'settings'] as $table) {
                DB::statement('DROP TABLE IF EXISTS `'.$this->table($table).'`');
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
        DB::disconnect();
        parent::tearDown();
    }

    #[Test]
    public function grouped_and_global_claim_plans_use_their_indexes_without_filesort(): void
    {
        $releases = $this->table('releases');
        DB::statement(<<<SQL
            INSERT INTO `{$releases}` (id, guid, groups_id, categories_id, nzbstatus, postdate, nzb_creation_claimed_at)
            SELECT seq, CONCAT('guid-', seq), MOD(seq, 20) + 1, 1,
                   IF(MOD(seq, 25) = 0, 1, 0), DATE_SUB(NOW(), INTERVAL seq SECOND),
                   IF(MOD(seq, 997) = 0, NOW(), NULL)
            FROM seq_1_to_50000
            SQL);
        DB::statement("ANALYZE TABLE `{$releases}`");

        $groupPlan = $this->explain(<<<SQL
            SELECT id FROM `{$releases}`
            WHERE nzbstatus = 0 AND groups_id = 7
              AND (nzb_creation_claimed_at IS NULL OR nzb_creation_claimed_at < DATE_SUB(NOW(), INTERVAL 300 SECOND))
            ORDER BY postdate DESC, id ASC LIMIT 100 FOR UPDATE
            SQL);
        $globalPlan = $this->explain(<<<SQL
            SELECT id FROM `{$releases}`
            WHERE nzbstatus = 0
              AND (nzb_creation_claimed_at IS NULL OR nzb_creation_claimed_at < DATE_SUB(NOW(), INTERVAL 300 SECOND))
            ORDER BY postdate DESC, id ASC LIMIT 100 FOR UPDATE
            SQL);

        $this->assertStringContainsString('ix_releases_nzb_creation_group_queue', $groupPlan);
        $this->assertStringContainsString('ix_releases_nzb_creation_global_queue', $globalPlan);
        $this->assertStringNotContainsString('filesort', strtolower($groupPlan));
        $this->assertStringNotContainsString('filesort', strtolower($globalPlan));
    }

    #[Test]
    public function workers_cannot_claim_the_same_nzb_rows(): void
    {
        DB::table('releases')->insert(array_map(static fn (int $id): array => [
            'id' => $id,
            'guid' => 'claim-'.$id,
            'groups_id' => 1,
            'categories_id' => 1,
            'nzbstatus' => 0,
            'postdate' => now()->subSeconds($id),
        ], range(1, 10)));

        $first = NzbCreationCandidateQuery::claimBatch(1, 5, 'worker-one', ['id', 'categories_id']);
        $second = NzbCreationCandidateQuery::claimBatch(1, 5, 'worker-two', ['id', 'categories_id']);

        $this->assertSame([1, 2, 3, 4, 5], $first->pluck('id')->all());
        $this->assertSame([6, 7, 8, 9, 10], $second->pluck('id')->all());
        $this->assertSame([], array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
    }

    private function createSchema(): void
    {
        DB::statement('CREATE TABLE `'.$this->table('settings').'` (name VARCHAR(255) PRIMARY KEY, value TEXT) ENGINE=InnoDB');
        DB::statement('CREATE TABLE `'.$this->table('root_categories').'` (id INT PRIMARY KEY, title VARCHAR(255)) ENGINE=InnoDB');
        DB::statement('CREATE TABLE `'.$this->table('categories').'` (id INT PRIMARY KEY, title VARCHAR(255), root_categories_id INT) ENGINE=InnoDB');
        DB::statement(<<<SQL
            CREATE TABLE `{$this->table('releases')}` (
                id INT UNSIGNED PRIMARY KEY, guid VARCHAR(64) NOT NULL, groups_id INT UNSIGNED NOT NULL,
                categories_id INT NOT NULL, nzbstatus TINYINT NOT NULL DEFAULT 0, postdate DATETIME,
                nzb_creation_claimed_at TIMESTAMP NULL, nzb_creation_claim_token VARCHAR(64) NULL,
                KEY ix_releases_nzb_creation_group_queue (nzbstatus, groups_id, postdate DESC, id, nzb_creation_claimed_at),
                KEY ix_releases_nzb_creation_global_queue (nzbstatus, postdate DESC, id, nzb_creation_claimed_at)
            ) ENGINE=InnoDB
            SQL);
        DB::statement('CREATE TABLE `'.$this->table('release_nzb_creation_failures').'` (
            releases_id INT UNSIGNED PRIMARY KEY, attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_error TEXT NULL, created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            FOREIGN KEY (releases_id) REFERENCES `'.$this->table('releases').'` (id) ON DELETE CASCADE
        ) ENGINE=InnoDB');
        DB::table('settings')->insert([
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
            ['name' => 'releaseprocessingtimeout', 'value' => '120'],
        ]);
        DB::table('root_categories')->insert(['id' => 1, 'title' => 'Other']);
        DB::table('categories')->insert(['id' => 1, 'title' => 'Misc', 'root_categories_id' => 1]);
    }

    private function explain(string $sql): string
    {
        $row = DB::selectOne('EXPLAIN FORMAT=JSON '.$sql);

        return (string) reset($row);
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
