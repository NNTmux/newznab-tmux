<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Api\RSS;
use App\Http\Middleware\TrustedDevice2FAMiddleware;
use App\Models\TrustedDevice;
use App\Models\User;
use App\View\Composers\GlobalDataComposer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $response->assertBadRequest();
        $response->assertSee('<error code="200" description="Missing parameter"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_legacy_api_get_without_apikey_returns_api_error_instead_of_login_redirect(): void
    {
        $response = $this->get('/api/v1/api?t=get&id=test-guid');

        $response->assertBadRequest();
        $response->assertSee('<error code="200" description="Missing parameter (apikey)"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_api_v2_getnzb_without_api_token_returns_json_error_instead_of_login_redirect(): void
    {
        $response = $this->getJson('/api/v2/getnzb?id=test-guid');

        $response->assertBadRequest();
        $response->assertJson([
            'error' => 'Missing parameter (api_token)',
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

        $response->assertUnauthorized();
        $response->assertSee('<error code="100" description="Incorrect user credentials"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_logged_in_unverified_user_cannot_download_nzb_via_session(): void
    {
        $userId = DB::table('users')->insertGetId([
            'username' => 'unverified-session-nzb-user',
            'email' => 'unverified-session-nzb@example.test',
            'password' => 'secret',
            'api_token' => 'unverified-session-nzb-token',
            'verified' => 0,
            'email_verified_at' => null,
        ]);
        $user = User::query()->findOrFail($userId);

        $response = $this->actingAs($user)->get('/getnzb?id=test-guid');

        $response->assertUnauthorized();
        $response->assertSee('<error code="100" description="Incorrect user credentials"/>', false);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_logged_in_unverified_user_is_redirected_away_from_site_pages(): void
    {
        $userId = DB::table('users')->insertGetId([
            'username' => 'unverified-site-user',
            'email' => 'unverified-site@example.test',
            'password' => 'secret',
            'api_token' => 'unverified-site-token',
            'verified' => 0,
            'email_verified_at' => null,
        ]);
        $user = User::query()->findOrFail($userId);

        $this->actingAs($user)
            ->get('/profile')
            ->assertRedirect(route('verification.notice'));
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

        $response->assertUnauthorized();
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

        $response->assertUnauthorized();
        $response->assertJson([
            'error' => 'Incorrect user credentials',
        ]);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_api_inform_rejects_unverified_users(): void
    {
        DB::table('users')->insert([
            'username' => 'unverified-inform-user',
            'email' => 'unverified-inform@example.test',
            'password' => 'secret',
            'api_token' => 'unverified-inform-token',
            'verified' => 0,
            'email_verified_at' => null,
        ]);

        $this->getJson('/api/inform/release?api_token=unverified-inform-token&relo=old.name&relp=new.name')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Incorrect user credentials');
    }

    public function test_rss_feed_without_api_token_returns_403_error_instead_of_login_redirect(): void
    {
        $response = $this->get('/rss/full-feed');

        $response->assertBadRequest();
        $response->assertJson([
            'error' => 'Missing parameter (api_token)',
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

        $response->assertUnauthorized();
        $response->assertJson([
            'error' => 'Incorrect user credentials',
        ]);
        $response->assertDontSee('name="login"', false);
        $response->assertDontSee('<title>Login', false);
    }

    public function test_authenticated_rss_feed_returns_output_response_body(): void
    {
        DB::table('roles')->insert([
            'id' => 1,
            'name' => 'User',
            'guard_name' => 'web',
            'apirequests' => 100,
            'downloadrequests' => 10,
        ]);

        DB::table('users')->insert([
            'username' => 'valid-rss-user',
            'email' => 'valid-rss@example.test',
            'password' => 'secret',
            'roles_id' => 1,
            'api_token' => 'valid-rss-token',
            'rate_limit' => 60,
            'verified' => 1,
            'email_verified_at' => now(),
        ]);

        $this->app->instance(RSS::class, new class extends RSS
        {
            public function __construct() {}

            public function getRss(mixed $cat, mixed $videosId, mixed $aniDbID, int $userID = 0, int $airDate = -1, int $limit = 100, int $offset = 0)
            {
                return [];
            }

            public function output(mixed $data, array $params, bool $xml, int $offset, string $type = '', array $headers = [])
            {
                return response('<rss>ok</rss>', 200, array_merge(['Content-type' => 'text/xml'], $headers));
            }
        });

        $this->get('/rss/full-feed?api_token=valid-rss-token')
            ->assertOk()
            ->assertSee('<rss>ok</rss>', false);
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
            ->assertJsonPath('error', 'Request limit reached');

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
            ->assertJsonPath('error', 'Request limit reached');
    }

    public function test_api_rate_limit_accepts_legacy_apikey_parameter(): void
    {
        DB::table('users')->insert([
            'username' => 'legacy-low-limit-user',
            'email' => 'legacy-low@example.test',
            'password' => 'secret',
            'api_token' => 'legacy-low-limit-token',
            'rate_limit' => 1,
            'verified' => 1,
        ]);

        $this->getJson('/api/test-rate-limit?apikey=legacy-low-limit-token')
            ->assertOk()
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');

        $this->getJson('/api/test-rate-limit?apikey=legacy-low-limit-token')
            ->assertStatus(429)
            ->assertJsonPath('error', 'Request limit reached');
    }

    public function test_api_v2_enforces_daily_role_request_quota(): void
    {
        DB::table('roles')->insert([
            'id' => 10,
            'name' => 'Limited',
            'guard_name' => 'web',
            'apirequests' => 1,
            'downloadrequests' => 10,
        ]);

        $userId = DB::table('users')->insertGetId([
            'username' => 'quota-v2-user',
            'email' => 'quota-v2@example.test',
            'password' => 'secret',
            'roles_id' => 10,
            'api_token' => 'quota-v2-token',
            'rate_limit' => 60,
            'verified' => 1,
            'email_verified_at' => now(),
        ]);

        DB::table('user_requests')->insert([
            ['users_id' => $userId, 'request' => '/api/v2/search?api_token=quota-v2-token&id=one', 'timestamp' => now()->subMinutes(10)],
            ['users_id' => $userId, 'request' => '/api/v2/search?api_token=quota-v2-token&id=two', 'timestamp' => now()->subMinutes(5)],
        ]);

        $this->getJson('/api/v2/search?api_token=quota-v2-token&id=test')
            ->assertStatus(429)
            ->assertHeader('X-NNTmux', 'API ERROR [500] Request limit reached')
            ->assertJsonPath('error', 'Request limit reached');
    }

    public function test_forged_trusted_device_cookie_does_not_pass_2fa_without_stored_token(): void
    {
        config(['google2fa.session_var' => 'google2fa']);

        $userId = DB::table('users')->insertGetId([
            'username' => 'forged-2fa-cookie-user',
            'email' => 'forged-2fa-cookie@example.test',
            'password' => 'secret',
            'api_token' => 'forged-2fa-cookie-token',
            'verified' => 1,
            'email_verified_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);
        Auth::login($user);

        $forgedCookie = json_encode([
            'user_id' => $user->id,
            'token' => 'client-forged-token-only',
            'expires_at' => time() + 3600,
        ], JSON_THROW_ON_ERROR);

        $request = Request::create('/trusted-device-check', 'GET', [], [
            '2fa_trusted_device' => $forgedCookie,
        ]);
        $request->setLaravelSession(app('session.store'));

        (new TrustedDevice2FAMiddleware)->handle($request, fn () => response('ok'));

        $this->assertFalse((bool) $request->session()->get('google2fa', false));
    }

    public function test_stored_trusted_device_cookie_passes_2fa(): void
    {
        config(['google2fa.session_var' => 'google2fa']);

        $userId = DB::table('users')->insertGetId([
            'username' => 'stored-2fa-cookie-user',
            'email' => 'stored-2fa-cookie@example.test',
            'password' => 'secret',
            'api_token' => 'stored-2fa-cookie-token',
            'verified' => 1,
            'email_verified_at' => now(),
        ]);
        $user = User::query()->findOrFail($userId);
        Auth::login($user);

        $trustedDevice = TrustedDevice::issueForUser($user, '127.0.0.1', 'Feature Test');
        $cookieValue = json_encode([
            'user_id' => $user->id,
            'token' => $trustedDevice['plain'],
            'expires_at' => $trustedDevice['device']->expires_at->getTimestamp(),
        ], JSON_THROW_ON_ERROR);

        $request = Request::create('/trusted-device-check', 'GET', [], [
            '2fa_trusted_device' => $cookieValue,
        ]);
        $request->setLaravelSession(app('session.store'));

        (new TrustedDevice2FAMiddleware)->handle($request, fn () => response('ok'));

        $this->assertTrue((bool) $request->session()->get('google2fa', false));
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
                $table->unsignedInteger('roles_id')->default(1);
                $table->string('api_token')->nullable()->index();
                $table->integer('rate_limit')->default(60);
                $table->boolean('verified')->default(true);
                $table->timestamp('apiaccess')->nullable();
                $table->string('host')->nullable();
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('name');
                $table->string('guard_name')->default('web');
                $table->integer('apirequests')->default(0);
                $table->integer('downloadrequests')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('model_has_roles')) {
            Schema::create('model_has_roles', function (Blueprint $table): void {
                $table->unsignedInteger('role_id');
                $table->string('model_type');
                $table->unsignedInteger('model_id');

                $table->primary(['role_id', 'model_id', 'model_type']);
            });
        }

        if (! Schema::hasTable('user_requests')) {
            Schema::create('user_requests', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('users_id');
                $table->text('request')->nullable();
                $table->timestamp('timestamp')->nullable();
            });
        }

        if (! Schema::hasTable('user_downloads')) {
            Schema::create('user_downloads', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('users_id');
                $table->timestamp('timestamp')->nullable();
            });
        }

        if (! Schema::hasTable('trusted_devices')) {
            Schema::create('trusted_devices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('token_hash', 64)->unique();
                $table->timestamp('expires_at');
                $table->timestamp('last_used_at')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->timestamps();
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
