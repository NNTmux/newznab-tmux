<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

class NzbAndRssAccessTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-nzb-rss-access-test.sqlite';

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
            ('title', 'NNTmux Test'),
            ('home_link', '/')");

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
            'mail.from.address' => 'noreply@example.test',
            'mail.from.name' => 'NNTmux Tests',
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

        DB::purge();
        DB::reconnect();
        Cache::flush();
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

    public function test_getnzb_without_token_returns_api_error_instead_of_login_redirect(): void
    {
        $response = $this->get('/getnzb?id=test-guid');

        $response->assertOk();
        $response->assertSee('<error code="200" description="Missing parameter"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_rss_feed_without_api_token_returns_403_error_instead_of_login_redirect(): void
    {
        $response = $this->get('/rss/full-feed');

        $response->assertForbidden();
        $response->assertJson([
            'error' => 'API key is required for viewing the RSS!',
        ]);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
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
