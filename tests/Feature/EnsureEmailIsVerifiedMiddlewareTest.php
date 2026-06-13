<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;
use PDO;
use Tests\TestCase;

class EnsureEmailIsVerifiedMiddlewareTest extends TestCase
{
    private string $databasePath;

    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-email-verified-test.sqlite';

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
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        ]);

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

    public function test_is_verified_alias_resolves_to_ensure_email_is_verified(): void
    {
        $router = app()->make(Router::class);
        $middlewareAliases = $router->getMiddleware();

        $this->assertArrayHasKey('isVerified', $middlewareAliases);
        $this->assertSame(
            EnsureEmailIsVerified::class,
            $middlewareAliases['isVerified']
        );
    }

    public function test_old_custom_middleware_is_not_registered_as_is_verified(): void
    {
        $router = app()->make(Router::class);
        $middlewareAliases = $router->getMiddleware();

        if (isset($middlewareAliases['isVerified'])) {
            $this->assertStringContainsString(
                'EnsureEmailIsVerified',
                $middlewareAliases['isVerified']
            );
        }
    }

    public function test_old_set_user_timezone_middleware_not_in_global_stack(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Http/Middleware/SetUserTimezone.php')
        );
    }

    public function test_old_check_for_maintenance_mode_middleware_not_in_global_stack(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Http/Middleware/CheckForMaintenanceMode.php')
        );
    }

    public function test_old_ensure_authenticated_users_verified_deleted(): void
    {
        $this->assertFileDoesNotExist(
            app_path('Http/Middleware/EnsureAuthenticatedUsersAreVerified.php')
        );
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
