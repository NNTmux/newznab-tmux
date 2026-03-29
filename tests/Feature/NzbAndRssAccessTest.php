<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PDO;
use ReflectionClass;
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

        $this->createSchema();
        $this->registerTestRoutes();
        $this->resetGlobalComposerState();
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

    public function test_legacy_api_get_without_apikey_returns_api_error_instead_of_login_redirect(): void
    {
        $response = $this->get('/api/v1/api?t=get&id=test-guid');

        $response->assertOk();
        $response->assertSee('<error code="200" description="Missing parameter (apikey)"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_api_v2_getnzb_without_api_token_returns_json_error_instead_of_login_redirect(): void
    {
        $response = $this->getJson('/api/v2/getnzb?id=test-guid');

        $response->assertForbidden();
        $response->assertJson([
            'error' => 'Missing or invalid API key',
        ]);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_getnzb_with_unverified_token_returns_api_credentials_error(): void
    {
        DB::table('users')->insert([
            'username' => 'unverified-nzb-user',
            'email' => 'unverified-nzb@example.test',
            'password' => 'secret',
            'api_token' => 'unverified-nzb-token',
            'verified' => 0,
            'email_verified_at' => null,
        ]);

        $response = $this->get('/getnzb?r=unverified-nzb-token&id=test-guid');

        $response->assertOk();
        $response->assertSee('<error code="100" description="Incorrect user credentials"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_legacy_api_rejects_unverified_users(): void
    {
        DB::table('users')->insert([
            'username' => 'unverified-api-user',
            'email' => 'unverified-api@example.test',
            'password' => 'secret',
            'api_token' => 'unverified-api-token',
            'verified' => 0,
            'email_verified_at' => null,
        ]);

        $response = $this->get('/api/v1/api?t=search&apikey=unverified-api-token');

        $response->assertOk();
        $response->assertSee('<error code="100" description="Incorrect user credentials (wrong API key)"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_api_v2_rejects_unverified_users(): void
    {
        DB::table('users')->insert([
            'username' => 'unverified-api-v2-user',
            'email' => 'unverified-api-v2@example.test',
            'password' => 'secret',
            'api_token' => 'unverified-api-v2-token',
            'verified' => 0,
            'email_verified_at' => null,
        ]);

        $response = $this->getJson('/api/v2/search?api_token=unverified-api-v2-token&id=test');

        $response->assertForbidden();
        $response->assertJson([
            'error' => 'Missing or invalid API key',
        ]);
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

    public function test_rss_feed_rejects_unverified_users(): void
    {
        DB::table('users')->insert([
            'username' => 'unverified-rss-user',
            'email' => 'unverified-rss@example.test',
            'password' => 'secret',
            'api_token' => 'unverified-rss-token',
            'verified' => 0,
            'email_verified_at' => null,
        ]);

        $response = $this->get('/rss/full-feed?api_token=unverified-rss-token');

        $response->assertForbidden();
        $response->assertJson([
            'error' => 'Invalid RSS token',
        ]);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_contact_form_is_publicly_accessible_to_guests(): void
    {
        $response = $this->get('/contact-us');

        $response->assertOk();
        $response->assertSee('Contact '.config('app.name'));
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_api_v2_rate_limit_uses_each_users_configured_rate_limit(): void
    {
        DB::table('users')->insert([
            [
                'username' => 'low-limit-user',
                'email' => 'low@example.test',
                'password' => 'secret',
                'api_token' => 'low-limit-token',
                'rate_limit' => 1,
                'verified' => 1,
            ],
            [
                'username' => 'high-limit-user',
                'email' => 'high@example.test',
                'password' => 'secret',
                'api_token' => 'high-limit-token',
                'rate_limit' => 3,
                'verified' => 1,
            ],
        ]);

        $this->getJson('/api/test-rate-limit?api_token=low-limit-token')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->getJson('/api/test-rate-limit?api_token=low-limit-token')
            ->assertStatus(429)
            ->assertJsonPath('error', 'API rate limit exceeded.');

        $this->getJson('/api/test-rate-limit?api_token=high-limit-token')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '3')
            ->assertHeader('X-RateLimit-Remaining', '2');

        $this->getJson('/api/test-rate-limit?api_token=high-limit-token')
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '1');

        $this->getJson('/api/test-rate-limit?api_token=high-limit-token')
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->getJson('/api/test-rate-limit?api_token=high-limit-token')
            ->assertStatus(429)
            ->assertJsonPath('error', 'API rate limit exceeded.');
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
        if (! Schema::hasTable('content')) {
            Schema::create('content', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title')->default('');
                $table->string('url', 2000)->nullable();
                $table->text('body')->nullable();
                $table->string('metadescription', 1000)->default('');
                $table->string('metakeywords', 1000)->default('');
                $table->integer('contenttype')->default(2);
                $table->integer('status')->default(1);
                $table->integer('ordinal')->nullable();
                $table->integer('role')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('username')->unique();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('api_token')->nullable()->index();
                $table->integer('rate_limit')->default(60);
                $table->boolean('verified')->default(true);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function registerTestRoutes(): void
    {
        if (! Route::has('tests.api-rate-limit')) {
            Route::middleware('apiRateLimit')
                ->get('/api/test-rate-limit', fn () => response()->json(['ok' => true]))
                ->name('tests.api-rate-limit');
        }
    }

    private function resetGlobalComposerState(): void
    {
        $reflection = new ReflectionClass(GlobalDataComposer::class);
        $property = $reflection->getProperty('resolvedData');
        $property->setValue(null, null);
    }
}
