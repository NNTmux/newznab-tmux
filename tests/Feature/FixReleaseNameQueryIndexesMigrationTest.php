<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class FixReleaseNameQueryIndexesMigrationTest extends TestCase
{
    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    private string $databasePath;

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-name-fixing-indexes-test', '.sqlite');
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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '1')");

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

    public function test_it_adds_only_missing_reverse_lookup_and_queue_indexes_idempotently(): void
    {
        $migration = require database_path('migrations/2026_08_04_082439_add_fix_release_name_query_indexes.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('par_hashes', 'ix_par_hashes_hash_releases_id'));
        $this->assertTrue(Schema::hasIndex('release_files', 'ix_release_files_crc32_releases_id'));
        $this->assertTrue(Schema::hasIndex('predb', 'ix_predb_searched_predate_id'));

        $migration->down();

        $this->assertFalse(Schema::hasIndex('par_hashes', 'ix_par_hashes_hash_releases_id'));
        $this->assertFalse(Schema::hasIndex('release_files', 'ix_release_files_crc32_releases_id'));
        $this->assertFalse(Schema::hasIndex('predb', 'ix_predb_searched_predate_id'));
    }

    private function createSchema(): void
    {
        Schema::create('par_hashes', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id');
            $table->string('hash', 32);
            $table->primary(['releases_id', 'hash']);
        });
        Schema::create('release_files', function (Blueprint $table): void {
            $table->unsignedInteger('releases_id');
            $table->string('name');
            $table->string('crc32')->default('');
            $table->primary(['releases_id', 'name']);
        });
        Schema::create('predb', function (Blueprint $table): void {
            $table->increments('id');
            $table->tinyInteger('searched')->default(0)->index();
            $table->dateTime('predate')->nullable()->index();
        });
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
