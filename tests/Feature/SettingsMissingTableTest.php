<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Settings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

/**
 * On a fresh install the settings table does not exist yet, while console boot
 * eagerly builds commands whose service constructors read settings. The model
 * must degrade to null instead of crashing (see NNTmux/newznab-tmux#1866).
 */
final class SettingsMissingTableTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-settings-missing-table-test', '.sqlite');

        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        // Deliberately create an empty database file: no settings table.
        new PDO('sqlite:'.$this->databasePath);

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

    public function test_setting_value_returns_null_when_settings_table_is_missing(): void
    {
        $this->assertNull(Settings::settingValue('categorizeforeign'));
    }

    public function test_setting_value_returns_converted_value_when_table_exists(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('delaytime', '42')");

        $this->assertSame(42, Settings::settingValue('delaytime'));
    }

    public function test_setting_value_rethrows_unrelated_query_errors(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE settings (name VARCHAR PRIMARY KEY, value TEXT NULL)');
        $pdo->exec("INSERT INTO settings (name, value) VALUES ('delaytime', '5')");

        // A locked database must not be masked as a missing table.
        // Keep the default 60s sqlite busy timeout from slowing the suite down.
        DB::connection()->getPdo()->setAttribute(PDO::ATTR_TIMEOUT, 1);

        $locker = new PDO('sqlite:'.$this->databasePath);
        $locker->exec('BEGIN EXCLUSIVE TRANSACTION');
        $locker->exec("INSERT INTO settings (name, value) VALUES ('lockholder', '1')");

        try {
            $this->expectException(QueryException::class);

            Settings::settingValue('delaytime');
        } finally {
            $locker->exec('ROLLBACK');
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
}
