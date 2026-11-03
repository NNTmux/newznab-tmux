<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Release;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class ReleaseFactoryInvariantsTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-release-factory.sqlite';

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        $pdo = new PDO('sqlite:'.$this->databasePath);
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->databasePath]);
        DB::purge();
        DB::reconnect();
        DB::statement('CREATE TABLE releases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(255) NOT NULL DEFAULT "",
            searchname VARCHAR(255) NOT NULL DEFAULT "",
            fromname VARCHAR(255) NULL,
            postdate DATETIME NULL,
            adddate DATETIME NULL,
            guid CHAR(36) NOT NULL UNIQUE,
            leftguid CHAR(1) NOT NULL,
            categories_id INTEGER NOT NULL DEFAULT 10,
            nzbstatus INTEGER NOT NULL DEFAULT 0,
            passwordstatus INTEGER NOT NULL DEFAULT -1,
            isrenamed INTEGER NOT NULL DEFAULT 0
        )');
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
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

    public function test_factory_produces_uuid_guid_with_matching_leftguid(): void
    {
        for ($iteration = 0; $iteration < 5; $iteration++) {
            $attributes = Release::factory()->raw();

            $this->assertMatchesRegularExpression(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD',
                (string) $attributes['guid'],
                'The factory must produce a UUID guid that fits the char(36) column.',
            );
            $this->assertSame(36, mb_strlen((string) $attributes['guid']));
            $this->assertSame(
                mb_strtolower(mb_substr((string) $attributes['guid'], 0, 1)),
                mb_strtolower((string) $attributes['leftguid']),
                'leftguid must be the first character of guid.',
            );
        }
    }

    public function test_factory_rows_satisfy_the_release_table_constraints(): void
    {
        foreach (Release::factory()->count(3)->raw() as $attributes) {
            DB::table('releases')->insert($attributes);
        }

        $rows = DB::table('releases')->get(['guid', 'leftguid']);

        $this->assertCount(3, $rows);
        foreach ($rows as $row) {
            $this->assertSame(mb_substr((string) $row->guid, 0, 1), (string) $row->leftguid);
        }
        $this->assertSame(3, $rows->pluck('guid')->unique()->count());
    }
}
