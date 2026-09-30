<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Facades\Search;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FailedReleasesControllerSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(random_bytes(32)),
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'session.driver' => 'array',
        ]);
        DB::purge();
        DB::reconnect();
        $this->createSchema();
        $this->seedSettings();
        $this->app->instance(PreventRequestForgery::class, new class(app(), app('encrypter')) extends PreventRequestForgery
        {
            protected function runningUnitTests(): bool
            {
                return false;
            }
        });
    }

    public function test_failed_callback_rejects_userid_without_session_or_api_token(): void
    {
        $this->createUser(1, 'rss-token');
        Search::shouldReceive('searchReleases')->never();
        $this->get(route('failed', [
            'guid' => 'failed-guid',
            'userid' => 1,
        ]))->assertStatus(405);
    }

    #[DataProvider('downloaderMethods')]
    public function test_failed_callback_uses_api_token_user_and_preserves_token_for_alternate_download(string $method, bool $withSession): void
    {
        $this->createUser(1, 'rss-token');
        $this->createUser(999, 'other-token');
        $this->createRelease(10, 'failed-guid', 'Show.Name.1080p.WEB-DL-GROUP', 5000, '2026-06-11 00:00:00');
        $this->createRelease(20, 'alternate-guid', 'Show.Name.1080p.PROPER-GROUP', 5000, '2026-06-12 00:00:00');
        Search::shouldReceive('searchReleases')
            ->once()
            ->with('Show.Name.1080p', 10)
            ->andReturn([20]);
        if ($withSession) {
            $this->actingAs(User::findOrFail(999));
        }
        $response = $this->{$method}(route('failed', [
            'guid' => 'failed-guid',
            'userid' => 999,
            'api_token' => 'rss-token',
        ]));
        $response->assertOk();
        $this->assertSame(
            url('/getnzb').'?id=alternate-guid&r=rss-token',
            $response->headers->get('Location'),
        );
        $this->assertDatabaseHas('dnzb_failures', [
            'release_id' => 10,
            'users_id' => 1,
            'failed' => 1,
        ]);
        $this->assertDatabaseMissing('dnzb_failures', [
            'release_id' => 10,
            'users_id' => 999,
        ]);
    }

    /** @return array<string, array{string, bool}> */
    public static function downloaderMethods(): array
    {
        return [
            'GET without session' => ['get', false],
            'POST without session' => ['post', false],
            'GET with another user session' => ['get', true],
            'POST with another user session' => ['post', true],
        ];
    }

    public function test_browser_get_cannot_record_a_failure_even_when_signed_in(): void
    {
        $this->createUser(1, 'rss-token');
        $this->createRelease(10, 'failed-guid', 'Show.Name.1080p.WEB-DL-GROUP', 5000, '2026-06-11 00:00:00');
        $this->actingAs(User::findOrFail(1))
            ->get(route('failed', ['guid' => 'failed-guid']))->assertStatus(405);
        $this->assertDatabaseCount('dnzb_failures', 0);
    }

    public function test_browser_post_requires_csrf_before_recording_a_failure(): void
    {
        $this->createUser(1, 'rss-token');
        $this->createRelease(10, 'failed-guid', 'Show.Name.1080p.WEB-DL-GROUP', 5000, '2026-06-11 00:00:00');
        $this->actingAs(User::findOrFail(1))->withSession(['_token' => 'csrf-token']);
        $this->post(route('failed'), ['guid' => 'failed-guid'])->assertStatus(419);
        $this->assertDatabaseCount('dnzb_failures', 0);
        $this->post(route('failed'), ['guid' => 'failed-guid', '_token' => 'wrong-token'])->assertStatus(419);
        $this->assertDatabaseCount('dnzb_failures', 0);

        Search::shouldReceive('searchReleases')->once()->andReturn([]);
        $this->post(route('failed'), ['guid' => 'failed-guid', '_token' => 'csrf-token'])
            ->assertStatus(404);
        $this->assertDatabaseHas('dnzb_failures', ['release_id' => 10, 'users_id' => 1, 'failed' => 1]);
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_api_tokens_cannot_fall_back_to_a_browser_session(mixed $token): void
    {
        $this->createUser(1, 'rss-token');
        $this->createRelease(10, 'failed-guid', 'Show.Name.1080p.WEB-DL-GROUP', 5000, '2026-06-11 00:00:00');
        $this->actingAs(User::findOrFail(1))->post(route('failed'), [
            'guid' => 'failed-guid', 'api_token' => $token,
        ])->assertStatus(401);
        $this->assertDatabaseCount('dnzb_failures', 0);
    }

    /** @return array<string, array{mixed}> */
    public static function invalidTokens(): array
    {
        return ['invalid' => ['invalid'], 'empty' => [''], 'array' => [['rss-token']]];
    }

    private function createSchema(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('name')->primary();
            $table->text('value')->nullable();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('username');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('roles_id')->default(1);
            $table->string('api_token')->nullable();
            $table->boolean('verified')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('releases', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('guid')->unique();
            $table->string('searchname');
            $table->unsignedInteger('categories_id');
            $table->timestamp('postdate')->nullable();
        });
        Schema::create('dnzb_failures', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('release_id');
            $table->unsignedInteger('users_id');
            $table->boolean('failed')->default(true);
        });
    }

    private function seedSettings(): void
    {
        DB::table('settings')->insert([
            ['name' => 'innerfileblacklist', 'value' => ''],
            ['name' => 'categorizeforeign', 'value' => '0'],
            ['name' => 'catwebdl', 'value' => '0'],
        ]);
    }

    private function createUser(int $id, string $apiToken): void
    {
        Cache::put(User::categoryExclusionCacheKey($id), []);
        DB::table('users')->insert([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => 'user'.$id.'@example.test',
            'password' => 'unused',
            'roles_id' => 1,
            'api_token' => $apiToken,
            'verified' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createRelease(int $id, string $guid, string $searchName, int $categoryId, string $postDate): void
    {
        DB::table('releases')->insert([
            'id' => $id,
            'guid' => $guid,
            'searchname' => $searchName,
            'categories_id' => $categoryId,
            'postdate' => $postDate,
        ]);
    }
}
