<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TvProcessingIndexMigrationTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    private string $databasePath;

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-tv-processing-index-'.getmypid().'.sqlite';
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

        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('videos_id')->default(0);
            $table->integer('tv_episodes_id')->default(0);
            $table->char('leftguid', 1);
            $table->dateTime('postdate')->nullable();
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

    #[Test]
    public function it_adds_and_removes_the_tv_processing_index_idempotently(): void
    {
        $migration = require database_path('migrations/2026_09_21_202739_add_tv_processing_index_to_releases_table.php');

        $migration->up();
        $migration->up();

        $index = collect(Schema::getIndexes('releases'))
            ->firstWhere('name', 'ix_releases_tv_processing');

        $this->assertNotNull($index);
        $this->assertSame(
            ['videos_id', 'tv_episodes_id', 'leftguid', 'postdate'],
            $index['columns'],
        );

        $migration->down();
        $migration->down();

        $this->assertFalse(Schema::hasIndex('releases', 'ix_releases_tv_processing'));
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
