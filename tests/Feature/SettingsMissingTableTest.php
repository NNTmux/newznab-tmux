<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ConfigurationDomain;
use App\Services\Configuration\ConfigurationProvider;
use App\Services\Configuration\DomainConfigurationRepository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Tests\TestCase;

final class SettingsMissingTableTest extends TestCase
{
    private string $databasePath = '';

    /** @var array<string, string|false> */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-typed-configuration-missing-table-test.sqlite';
        $this->originalEnvironment = [
            'APP_ENV' => getenv('APP_ENV'),
            'DB_CONNECTION' => getenv('DB_CONNECTION'),
            'DB_DATABASE' => getenv('DB_DATABASE'),
        ];

        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
        new PDO('sqlite:'.$this->databasePath);

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
        parent::tearDown();

        foreach ($this->originalEnvironment as $key => $value) {
            $this->setEnvironmentValue($key, $value === false ? null : $value);
        }
    }

    public function test_reads_return_definition_defaults_when_domain_tables_are_missing(): void
    {
        $site = app(ConfigurationProvider::class)->site();

        $this->assertSame('NNTmux', $site->title);
        $this->assertSame('/', $site->homeLink);
    }

    public function test_mutation_fails_clearly_when_domain_table_is_missing(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Site configuration table is not available');

        app(DomainConfigurationRepository::class)->update(ConfigurationDomain::Site, ['title' => 'Unavailable']);
    }

    public function test_reads_do_not_mask_unrelated_query_errors(): void
    {
        $pdo = new PDO('sqlite:'.$this->databasePath);
        $pdo->exec('CREATE TABLE site_configurations (id INTEGER PRIMARY KEY, title TEXT, home_link TEXT, site_logo TEXT NULL, strapline TEXT, meta_title TEXT, meta_description TEXT, meta_keywords TEXT, footer TEXT, dereferrer_link TEXT, terms TEXT, trailers_display INTEGER, trailers_size_x INTEGER, trailers_size_y INTEGER, created_at TEXT NULL, updated_at TEXT NULL)');
        $pdo->exec("INSERT INTO site_configurations VALUES (1, 'Test', '/', NULL, '', '', '', '', '', '', '', 1, 480, 345, NULL, NULL)");

        DB::connection()->getPdo()->setAttribute(PDO::ATTR_TIMEOUT, 1);
        $locker = new PDO('sqlite:'.$this->databasePath);
        $locker->exec('BEGIN EXCLUSIVE TRANSACTION');
        $locker->exec("UPDATE site_configurations SET title = 'Locked' WHERE id = 1");

        try {
            $this->expectException(QueryException::class);
            app(ConfigurationProvider::class)->site(fresh: true);
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
