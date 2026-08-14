<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PDO;
use ReflectionMethod;
use RuntimeException;
use Tests\Integration\ReleaseSchemaOptimizationMigrationMariaDbTest;
use Tests\TestCase;

/**
 * Portable coverage for the hardening added to the releases normalization
 * migration: the chunked comment recount, the batched rollback backfill, and
 * the preflight opt-out. The full schema rebuild is MariaDB-only and lives in
 * {@see ReleaseSchemaOptimizationMigrationMariaDbTest}.
 */
final class ReleaseSchemaOptimizationMigrationTest extends TestCase
{
    private string $databasePath = '';

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-releases-migration.sqlite';
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
            'nntmux.releases_optimize.chunk_size' => 100,
            'nntmux.releases_optimize.skip_preflight' => false,
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

    public function test_comment_recount_spans_multiple_chunks_and_skips_correct_rows(): void
    {
        $releases = [];
        $comments = [];
        $commentId = 1;
        for ($id = 1; $id <= 250; $id++) {
            // Every third release already stores the right counter and must not be rewritten.
            $alreadyCorrect = $id % 3 === 0;
            $releases[] = ['id' => $id, 'guid' => 'g'.$id, 'leftguid' => 'g', 'comments' => $alreadyCorrect ? 1 : 99];
            $comments[] = ['id' => $commentId++, 'releases_id' => $id, 'isvisible' => 1];
            $comments[] = ['id' => $commentId++, 'releases_id' => $id, 'isvisible' => 0];
        }
        DB::table('releases')->insert($releases);
        DB::table('release_comments')->insert($comments);

        $this->invoke('recountVisibleComments');

        $this->assertSame(0, DB::table('releases')->where('comments', '<>', 1)->count());
        $this->assertSame(250, DB::table('releases')->where('comments', 1)->count());
    }

    public function test_comment_recount_zeroes_releases_without_visible_comments(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'guid' => 'a', 'leftguid' => 'a', 'comments' => 7],
            ['id' => 2, 'guid' => 'b', 'leftguid' => 'b', 'comments' => 0],
        ]);
        DB::table('release_comments')->insert([['id' => 1, 'releases_id' => 1, 'isvisible' => 0]]);

        $this->invoke('recountVisibleComments');

        $this->assertSame(0, (int) DB::table('releases')->where('id', 1)->value('comments'));
        $this->assertSame(0, (int) DB::table('releases')->where('id', 2)->value('comments'));
    }

    public function test_rollback_backfill_restores_sparse_rows_in_batches(): void
    {
        $releases = [];
        $passwords = [];
        $failures = [];
        for ($id = 1; $id <= 250; $id++) {
            $releases[] = ['id' => $id, 'guid' => 'g'.$id, 'leftguid' => 'g', 'comments' => 0];
            $passwords[] = ['releases_id' => $id, 'password' => 'secret-'.$id];
            $failures[] = ['releases_id' => $id, 'attempts' => $id % 5, 'last_error' => 'boom-'.$id];
        }
        DB::table('releases')->insert($releases);
        DB::table('release_nzb_passwords')->insert($passwords);
        DB::table('release_nzb_creation_failures')->insert($failures);

        $this->invoke('restoreSparseDataToReleases');

        $restored = DB::table('releases')->orderBy('id')->get();
        $this->assertCount(250, $restored);
        foreach ($restored as $release) {
            $this->assertSame('secret-'.$release->id, $release->nzb_password);
            $this->assertSame((int) $release->id % 5, (int) $release->nzb_creation_attempts);
            $this->assertSame('boom-'.$release->id, $release->nzb_creation_last_error);
        }
    }

    public function test_preflight_blocks_bad_identifiers_and_the_opt_out_bypasses_it(): void
    {
        DB::table('releases')->insert([
            ['id' => 1, 'guid' => 'not-a-uuid', 'leftguid' => 'z', 'comments' => 0],
        ]);

        try {
            $this->invoke('assertReleaseIdentifiersAreSafe');
            $this->fail('The migration accepted an invalid guid.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('invalid release GUIDs', $e->getMessage());
            $this->assertStringContainsString('releases:normalize-guids', $e->getMessage());
        }

        config(['nntmux.releases_optimize.skip_preflight' => true]);
        $this->invoke('assertReleaseIdentifiersAreSafe');
        $this->assertSame(1, DB::table('releases')->count());
    }

    private function invoke(string $method): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_08_13_001652_normalize_and_optimize_releases_table.php');
        (new ReflectionMethod($migration, $method))->invoke($migration);
    }

    private function createSchema(): void
    {
        foreach (['release_comments', 'release_nzb_passwords', 'release_nzb_creation_failures', 'releases'] as $table) {
            DB::statement('DROP TABLE IF EXISTS '.$table);
        }
        DB::statement('CREATE TABLE releases (
            id INTEGER PRIMARY KEY, guid VARCHAR(40) NOT NULL, leftguid CHAR(1) NOT NULL,
            comments INTEGER NOT NULL DEFAULT 0, nzb_password VARCHAR(255) NULL,
            nzb_creation_attempts INTEGER NOT NULL DEFAULT 0, nzb_creation_last_error TEXT NULL
        )');
        DB::statement('CREATE TABLE release_comments (
            id INTEGER PRIMARY KEY, releases_id INTEGER NOT NULL, isvisible INTEGER NOT NULL DEFAULT 1
        )');
        DB::statement('CREATE TABLE release_nzb_passwords (
            releases_id INTEGER PRIMARY KEY, password VARCHAR(255) NOT NULL
        )');
        DB::statement('CREATE TABLE release_nzb_creation_failures (
            releases_id INTEGER PRIMARY KEY, attempts INTEGER NOT NULL DEFAULT 0, last_error TEXT NULL
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
