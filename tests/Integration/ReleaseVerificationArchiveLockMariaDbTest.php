<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\CollectionCleanupService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ManifestStore;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ReleaseVerificationArchiveLockMariaDbTest extends TestCase
{
    private ?PDO $administrator = null;

    private string $database = '';

    private string $host = '';

    private string $username = '';

    private string $password = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->host = (string) getenv('VERIFICATION_LOCK_TEST_HOST');
        if ($this->host === '') {
            $this->markTestSkipped('Set VERIFICATION_LOCK_TEST_HOST to an isolated MariaDB test server.');
        }
        $this->username = (string) (getenv('VERIFICATION_LOCK_TEST_USERNAME') ?: 'root');
        $this->password = (string) getenv('VERIFICATION_LOCK_TEST_PASSWORD');
        $this->administrator = new PDO('mysql:host='.$this->host.';charset=utf8mb4', $this->username, $this->password);
        $this->database = 'nntmux_archive_lock_test_'.bin2hex(random_bytes(8));
        $this->administrator->exec('CREATE DATABASE `'.$this->database.'`');
        config(['database.default' => 'mariadb', 'database.connections.mariadb.host' => $this->host,
            'database.connections.mariadb.port' => 3306, 'database.connections.mariadb.database' => $this->database,
            'database.connections.mariadb.username' => $this->username, 'database.connections.mariadb.password' => $this->password,
            'database.connections.mariadb.url' => null, 'database.connections.mariadb.prefix' => '',
            'release_verification.required' => true, 'nntmux.echocli' => false,
            'release_verification.storage_path' => storage_path('framework/testing/lock-'.bin2hex(random_bytes(8)))]);
        DB::purge();
        DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
        (require database_path('migrations/2026_10_11_154028_create_release_verification_candidates_table.php'))->up();
        DB::statement('CREATE TABLE collections (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            collectionhash BINARY(20) NOT NULL, subject VARCHAR(255) NOT NULL, fromname VARCHAR(255) NOT NULL,
            totalfiles INT NOT NULL, groups_id INT NOT NULL, collection_regexes_id INT DEFAULT 0,
            date DATETIME NOT NULL, filecheck INT DEFAULT 3, filesize BIGINT DEFAULT 5,
            UNIQUE KEY ix_collection_collectionhash (collectionhash)) ENGINE=InnoDB');
        DB::statement('CREATE TABLE binaries (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            collections_id BIGINT UNSIGNED NOT NULL, binaryhash BINARY(16) NOT NULL,
            name VARCHAR(255) NOT NULL, totalparts INT NOT NULL,
            FOREIGN KEY (collections_id) REFERENCES collections(id) ON DELETE CASCADE) ENGINE=InnoDB');
        DB::statement('CREATE TABLE parts (binaries_id BIGINT UNSIGNED NOT NULL, partnumber INT NOT NULL,
            messageid VARCHAR(255) NOT NULL, size INT NOT NULL, PRIMARY KEY (binaries_id, partnumber),
            FOREIGN KEY (binaries_id) REFERENCES binaries(id) ON DELETE CASCADE) ENGINE=InnoDB');
        DB::statement('CREATE TABLE usenet_groups (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL) ENGINE=InnoDB');
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->administrator !== null && preg_match('/^nntmux_archive_lock_test_[a-f0-9]{16}$/', $this->database) === 1) {
                app('files')->deleteDirectory((string) config('release_verification.storage_path'));
                DB::disconnect();
                $this->administrator->exec('DROP DATABASE `'.$this->database.'`');
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function heldCollectionIndexes(): iterable
    {
        yield 'primary row held' => [false];
        yield 'unique hash held' => [true];
    }

    #[DataProvider('heldCollectionIndexes')]
    public function test_locked_collection_keeps_capacity_free_and_retries_without_losing_headers(bool $hashIndex): void
    {
        $id = $this->arrival('busy');
        $otherId = $this->arrival('unrelated');
        $other = $this->connection();
        $other->beginTransaction();
        $lock = $other->prepare($hashIndex
            ? 'SELECT collectionhash FROM collections FORCE INDEX (ix_collection_collectionhash) WHERE collectionhash = ? LOCK IN SHARE MODE'
            : 'SELECT id FROM collections FORCE INDEX (PRIMARY) WHERE id = ? FOR UPDATE');
        $lock->execute([$hashIndex ? sha1('busy', true) : $id]);
        $lock->fetchAll();
        $capacityReads = 0;
        DB::listen(static function (QueryExecuted $query) use (&$capacityReads): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'release_verification_state')) {
                $capacityReads++;
            }
        });
        $repository = app(CandidateRepository::class);
        try {
            $repository->archive($id);
            $this->assertSame(0, $capacityReads);
            $this->assertSame(2, DB::table('collections')->count());
            $this->assertSame(2, DB::table('parts')->count());
            $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
            $repository->archive($otherId);
            $this->assertSame(1, DB::table('release_verification_candidates')->count());
            $this->assertSame(1, DB::table('collections')->count());
            $other->query('SELECT pending_bytes FROM release_verification_state WHERE id = 1 FOR UPDATE')->fetchAll();
            $capacityReads = 0;
            $repository->archive($id);
            $this->assertSame(0, $capacityReads);
        } finally {
            $other->rollBack();
        }

        $this->assertSame(0, app(CollectionCleanupService::class)->deleteCollectionsAndDescendants([$id]));
        $this->assertSame(1, DB::table('parts')->count());
        $repository->archive($id);
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(0, DB::table('parts')->count());
        $this->assertSame(2, DB::table('release_verification_candidates')->count());
        $this->assertSame((int) DB::table('release_verification_candidates')->sum('manifest_bytes'),
            (int) DB::table('release_verification_state')->value('pending_bytes'));
        $candidate = DB::table('release_verification_candidates')->where('source_identity', sha1('busy'))->first();
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertSame('busy@article', $manifest['files'][0]['segments'][0]['messageid']);
    }

    public function test_locking_lookup_uses_unique_hash_index_before_capacity(): void
    {
        $id = $this->arrival('plan');
        $key = null;
        DB::listen(static function (QueryExecuted $query) use (&$key): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from `collections`')
                && str_contains($query->sql, 'for update')) {
                $plan = DB::selectOne('EXPLAIN '.$query->sql, $query->bindings);
                $key = $plan->key;
            }
        });

        app(CandidateRepository::class)->archive($id);

        $this->assertSame('ix_collection_collectionhash', $key);
    }

    /** @return iterable<string, array{bool}> */
    public static function snapshotTransactionModes(): iterable
    {
        yield 'standalone archival' => [false];
        yield 'caller-owned transaction' => [true];
    }

    #[DataProvider('snapshotTransactionModes')]
    public function test_late_parts_committed_after_hash_read_are_preserved_in_repeatable_read(bool $outerTransaction): void
    {
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $id = $this->arrival('late');
        $binary = (int) DB::table('binaries')->where('collections_id', $id)->value('id');
        $other = $this->connection();
        $committed = false;
        DB::listen(static function (QueryExecuted $query) use ($id, $binary, $other, &$committed): void {
            if (! $committed && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'collectionhash')
                && str_contains($query->sql, 'collections') && $query->bindings === [$id]) {
                $committed = true;
                $other->beginTransaction();
                try {
                    $statement = $other->prepare('UPDATE collections SET filesize = filesize + 7 WHERE id = ?');
                    $statement->execute([$id]);
                    $statement = $other->prepare('UPDATE binaries SET totalparts = 2 WHERE id = ?');
                    $statement->execute([$binary]);
                    $statement = $other->prepare('INSERT INTO parts (binaries_id, partnumber, messageid, size) VALUES (?, 2, ?, 7)');
                    $statement->execute([$binary, 'late-second@article']);
                    $other->commit();
                } catch (\Throwable $error) {
                    $other->rollBack();
                    throw $error;
                }
            }
        });

        if ($outerTransaction) {
            DB::beginTransaction();
        }
        try {
            app(CandidateRepository::class)->archive($id);
            if ($outerTransaction) {
                DB::commit();
            }
        } catch (\Throwable $error) {
            if ($outerTransaction && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $error;
        }

        $this->assertTrue($committed);
        $candidate = DB::table('release_verification_candidates')->sole();
        $manifest = app(ManifestStore::class)->read($candidate->manifest_path);
        $this->assertSame(['late@article', 'late-second@article'], array_column($manifest['files'][0]['segments'], 'messageid'));
        $this->assertSame(2, $manifest['files'][0]['totalparts']);
        $this->assertSame(0, DB::table('collections')->count());
        $this->assertSame(0, DB::table('parts')->count());
    }

    public function test_missing_collection_does_not_wait_on_locked_capacity(): void
    {
        $other = $this->connection();
        $other->beginTransaction();
        $other->query('SELECT id FROM release_verification_state WHERE id = 1 FOR UPDATE')->fetchAll();
        try {
            app(CandidateRepository::class)->archive(999);
            $this->assertSame(0, DB::table('release_verification_candidates')->count());
        } finally {
            $other->rollBack();
        }
    }

    public function test_claim_skips_locked_due_head_and_leaves_capacity_available(): void
    {
        $repository = app(CandidateRepository::class);
        foreach (['first', 'second'] as $name) {
            $repository->enqueue(['identity' => sha1($name), 'groups_id' => 1, 'files' => []]);
        }
        $ids = DB::table('release_verification_candidates')->orderBy('id')->pluck('id')->all();
        $bytes = (int) DB::table('release_verification_state')->value('pending_bytes');
        $other = $this->connection();
        $other->beginTransaction();
        $statement = $other->prepare('SELECT id FROM release_verification_candidates FORCE INDEX (PRIMARY) WHERE id = ? FOR UPDATE');
        $statement->execute([$ids[0]]);
        $statement->fetchAll();
        $other->query('SELECT id FROM release_verification_state WHERE id = 1 FOR UPDATE')->fetchAll();
        try {
            $claim = $repository->claim();
            $this->assertSame((int) $ids[1], (int) $claim->id);
            $statement = $other->prepare('UPDATE release_verification_candidates SET status = ? WHERE id = ?');
            $statement->execute(['unknown', $ids[0]]);
        } finally {
            $other->commit();
        }
        $next = $repository->claim();
        $this->assertSame((int) $ids[0], (int) $next->id);
        $this->assertNotSame($claim->lease_token, $next->lease_token);
        $this->assertNull($repository->claim());
        $this->assertSame($bytes, (int) DB::table('release_verification_state')->value('pending_bytes'));
    }

    public function test_refused_claim_releases_its_row_before_trying_another_candidate(): void
    {
        $repository = app(CandidateRepository::class);
        foreach (['first', 'second'] as $name) {
            $repository->enqueue(['identity' => sha1($name), 'groups_id' => 1, 'files' => []]);
        }
        $ids = DB::table('release_verification_candidates')->orderBy('id')->pluck('id')->all();
        $other = $this->connection();
        $claim = $repository->claim(static function (int $id) use ($ids, $other): bool {
            if ($id === (int) $ids[0]) {
                return false;
            }
            $statement = $other->prepare('UPDATE release_verification_candidates SET next_attempt_at = ? WHERE id = ?');
            $statement->execute([now()->addHour()->format('Y-m-d H:i:s'), $ids[0]]);

            return true;
        });

        $this->assertSame((int) $ids[1], (int) $claim->id);
        $this->assertNull(DB::table('release_verification_candidates')->where('id', $ids[0])->value('lease_token'));
    }

    public function test_claim_revalidates_selected_ids_and_uses_primary_locking_plan(): void
    {
        $repository = app(CandidateRepository::class);
        foreach (['first', 'second'] as $name) {
            $repository->enqueue(['identity' => sha1($name), 'groups_id' => 1, 'files' => []]);
        }
        $ids = DB::table('release_verification_candidates')->orderBy('id')->pluck('id')->all();
        $other = $this->connection();
        $changed = false;
        $keys = [];
        DB::listen(static function (QueryExecuted $query) use ($ids, $other, &$changed, &$keys): void {
            if (! $changed && str_starts_with($query->sql, 'select `id` from `release_verification_candidates`')) {
                $changed = true;
                $statement = $other->prepare('UPDATE release_verification_candidates SET lease_until = ? WHERE id = ?');
                $statement->execute([now()->addHour()->format('Y-m-d H:i:s'), $ids[0]]);
            }
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'release_verification_candidates')
                && str_contains($query->sql, 'for update')) {
                $plan = DB::selectOne('EXPLAIN '.$query->sql, $query->bindings);
                if ($plan->key !== null) {
                    $keys[] = $plan->key;
                }
            }
        });

        $claim = $repository->claim();

        $this->assertTrue($changed);
        $this->assertSame((int) $ids[1], (int) $claim->id);
        $this->assertSame(['PRIMARY'], array_values(array_unique($keys)));
    }

    private function connection(): PDO
    {
        $pdo = new PDO('mysql:host='.$this->host.';dbname='.$this->database.';charset=utf8mb4', $this->username, $this->password);
        $pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');

        return $pdo;
    }

    private function arrival(string $identity): int
    {
        $id = DB::table('collections')->insertGetId(['collectionhash' => sha1($identity, true),
            'subject' => 'Movie', 'fromname' => 'poster', 'totalfiles' => 1, 'groups_id' => 1,
            'date' => now()->format('Y-m-d H:i:s')]);
        $binary = DB::table('binaries')->insertGetId(['collections_id' => $id, 'binaryhash' => md5($identity, true),
            'name' => 'movie.mkv', 'totalparts' => 1]);
        DB::table('parts')->insert(['binaries_id' => $binary, 'partnumber' => 1, 'messageid' => $identity.'@article', 'size' => 5]);

        return $id;
    }
}
