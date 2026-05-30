<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\AdditionalProcessing\AdditionalCandidateQuery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Tests\TestCase;

class AdditionalCandidateQueryTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-additional-candidate-query-test.sqlite';

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('categorizeforeign', '0'), ('catwebdl', '0')");

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
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_bucket_chars_preserve_alphabetic_guid_buckets(): void
    {
        DB::table('categories')->insert([
            ['id' => 1, 'disablepreview' => 0],
            ['id' => 2, 'disablepreview' => 1],
        ]);

        DB::table('releases')->insert([
            $this->releaseRow(1, '0'),
            $this->releaseRow(2, '9'),
            $this->releaseRow(3, 'a'),
            $this->releaseRow(4, 'a'),
            $this->releaseRow(5, 'f'),
            $this->releaseRow(6, 'b', categoriesId: 2),
        ]);

        $chars = AdditionalCandidateQuery::bucketChars();
        sort($chars);

        $this->assertSame(['0', '9', 'a', 'f'], $chars);
    }

    /**
     * @return array<string, mixed>
     */
    private function releaseRow(int $id, string $leftguid, int $categoriesId = 1): array
    {
        return [
            'id' => $id,
            'leftguid' => $leftguid,
            'passwordstatus' => -1,
            'haspreview' => -1,
            'nzbstatus' => 1,
            'categories_id' => $categoriesId,
            'size' => 2 * 1048576,
        ];
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

    private function createSchema(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        DB::table('settings')->upsert([
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ], ['name'], ['value']);

        Schema::dropIfExists('releases');
        Schema::dropIfExists('categories');

        Schema::create('categories', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->boolean('disablepreview')->default(false);
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->char('leftguid', 1);
            $table->integer('passwordstatus');
            $table->integer('haspreview');
            $table->integer('nzbstatus');
            $table->unsignedInteger('categories_id');
            $table->unsignedBigInteger('size');
        });
    }
}
