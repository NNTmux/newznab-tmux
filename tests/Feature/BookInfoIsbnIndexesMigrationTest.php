<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class BookInfoIsbnIndexesMigrationTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-bookinfo-isbn-indexes.sqlite';
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

        Schema::create('bookinfo', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('isbn')->nullable();
            $table->string('ean')->nullable();
        });
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

    public function test_it_adds_and_removes_isbn_indexes_idempotently(): void
    {
        $migration = require database_path('migrations/2026_08_13_133021_add_isbn_indexes_to_bookinfo_table.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasIndex('bookinfo', 'ix_bookinfo_isbn'));
        $this->assertTrue(Schema::hasIndex('bookinfo', 'ix_bookinfo_ean'));

        $migration->down();

        $this->assertFalse(Schema::hasIndex('bookinfo', 'ix_bookinfo_isbn'));
        $this->assertFalse(Schema::hasIndex('bookinfo', 'ix_bookinfo_ean'));
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
