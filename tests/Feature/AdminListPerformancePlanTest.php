<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use PDO;
use Tests\TestCase;

class AdminListPerformancePlanTest extends TestCase
{
    private string $databasePath = '';

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = $this->makeTempPath('nntmux-admin-list-performance-test', '.sqlite');

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
        $pdo->exec("INSERT INTO settings (name, value) VALUES
            ('categorizeforeign', '0'),
            ('catwebdl', '0'),
            ('innerfileblacklist', ''),
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

        $this->setEnvironmentValue('APP_ENV', 'testing');
        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', $this->databasePath);

        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        if ($this->databasePath !== '' && file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }

        parent::tearDown();
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

    public function test_release_admin_list_uses_versioned_cache_keys(): void
    {
        $modelPath = app_path('Models/Release.php');

        $this->assertFileExists($modelPath);

        $content = file_get_contents($modelPath);

        $this->assertStringContainsString('adminReleasesRangeVersion', $content);
        $this->assertStringContainsString(".'_'.(\$categoryId ?? 'all')", $content);
        $this->assertStringContainsString('Cache::forever(\'adminReleasesRangeVersion\'', $content);
    }

    public function test_group_admin_list_no_longer_groups_by_id(): void
    {
        $modelPath = app_path('Models/UsenetGroup.php');

        $this->assertFileExists($modelPath);

        $content = file_get_contents($modelPath);
        $methodStart = strpos($content, 'public static function getGroupsRange');
        $this->assertIsInt($methodStart);
        $methodBody = substr($content, $methodStart, 1200);

        $this->assertStringContainsString("->select([\n                'id',", $methodBody);
        $this->assertStringContainsString("->orderBy('name')", $methodBody);
        $this->assertStringNotContainsString('groupBy', $methodBody);
    }

    public function test_admin_list_index_migration_contains_narrow_indexes(): void
    {
        $migrationPath = database_path('migrations/2026_07_13_000000_add_admin_list_performance_indexes.php');

        $this->assertFileExists($migrationPath);

        $content = file_get_contents($migrationPath);

        $this->assertStringContainsString('ix_releases_categories_postdate_admin', $content);
        $this->assertStringContainsString('ix_releases_postdate_admin', $content);
        $this->assertStringContainsString('ix_usenet_groups_active_name_admin', $content);
        $this->assertStringContainsString('ix_release_reports_status_created_admin', $content);
    }
}
