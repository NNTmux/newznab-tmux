<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use PDO;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BtcPaymentLinkTest extends TestCase
{
    private string $databasePath;

    /**
     * @var array<string, string|false>
     */
    private array $originalEnvironment = [];

    public function createApplication()
    {
        $this->databasePath = sys_get_temp_dir().'/nntmux-btc-payment-link-test.sqlite';

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
        $pdo->exec('CREATE TABLE permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR NOT NULL,
            guard_name VARCHAR NOT NULL,
            created_at DATETIME NULL,
            updated_at DATETIME NULL
        )');
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

        Gate::before(static fn (): bool => false);
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

    public function test_btcpay_route_redirects_to_placeholder(): void
    {
        $this->get(route('btcpay'))
            ->assertRedirect('https://example.com');
    }

    public function test_user_sidebar_renders_upgrade_placeholder(): void
    {
        $this->actingAs($this->userWithRole('User'));

        $this->view('partials.sidebar')
            ->assertSee('href="https://example.com"', false)
            ->assertSee('Upgrade Your Account')
            ->assertDontSee('Extend Your Account');
    }

    public function test_supporter_sidebar_renders_extension_placeholder(): void
    {
        $this->actingAs($this->userWithRole('Supporter'));

        $this->view('partials.sidebar')
            ->assertSee('href="https://example.com"', false)
            ->assertSee('Extend Your Account')
            ->assertDontSee('Upgrade Your Account');
    }

    private function userWithRole(string $roleName): User
    {
        $user = new User([
            'consoleview' => false,
            'movieview' => false,
            'musicview' => false,
            'gameview' => false,
            'xxxview' => false,
            'bookview' => false,
        ]);
        $user->id = 1;
        $user->setRelation('roles', new Collection([new Role(['name' => $roleName])]));

        return $user;
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
