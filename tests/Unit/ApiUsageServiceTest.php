<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Jobs\RecordApiUsage;
use App\Jobs\UpdateUserApiAccess;
use App\Models\User;
use App\Services\Api\ApiUsageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ApiUsageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
        ]);
        DB::purge();
        DB::reconnect();
        Cache::flush();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('apiaccess')->nullable();
            $table->string('host')->nullable();
        });
        Schema::create('user_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('users_id');
            $table->text('request');
            $table->dateTime('timestamp');
        });
        DB::table('users')->insert(['id' => 1]);
    }

    public function test_async_record_persists_usage_before_dispatching_metadata_update(): void
    {
        Queue::fake();
        config(['nntmux.api.async_audit' => true]);
        $user = new User;
        $user->id = 1;
        $request = Request::create('/api/v2/search?api_token=secret&id=test', 'GET');

        (new ApiUsageService)->record($user, $request);

        Queue::assertPushed(UpdateUserApiAccess::class, static fn (UpdateUserApiAccess $job): bool => $job->userId === 1
            && $job->ip === '127.0.0.1'
        );
        $this->assertDatabaseHas('user_requests', [
            'users_id' => 1,
            'request' => '/api/v2/search?id=test',
        ]);
    }

    public function test_audit_entry_redacts_credentials_from_url_and_body_and_drops_unknown_fields(): void
    {
        $request = Request::create('/api/v2/search?api_token=url-secret&apikey=other-secret', 'QUERY', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], (string) json_encode(['id' => 'ubuntu', 'cat' => [2000, 5030], 'password' => 'hunter2']));

        $entry = (new ApiUsageService)->auditEntry($request);

        $this->assertSame('/api/v2/search?cat=2000%2C5030&id=ubuntu', $entry);
    }

    public function test_audit_entry_is_truncated_to_the_column_length(): void
    {
        $parameters = [];
        foreach (['q', 'id', 'imdbid', 'tmdbid', 'traktid', 'tvdbid'] as $key) {
            $parameters[$key] = str_repeat('a', 100);
        }
        $request = Request::create('/api/v1/api', 'GET', $parameters);

        $entry = (new ApiUsageService)->auditEntry($request);

        $this->assertSame(255, mb_strlen($entry));
        $this->assertStringNotContainsString(str_repeat('a', 65), $entry);
    }

    public function test_audit_entry_skips_legacy_array_values_without_a_type_error(): void
    {
        $request = Request::create('/api/v1/api?t=search&o[]=json&q=test', 'GET');

        $entry = (new ApiUsageService)->auditEntry($request);

        $this->assertSame('/api/v1/api?q=test&t=search', $entry);
    }

    public function test_audit_entry_keeps_empty_parameters_visible(): void
    {
        $request = Request::create('/api/v2/search', 'GET', ['id' => null, 't' => 'search']);

        $entry = (new ApiUsageService)->auditEntry($request);

        $this->assertSame('/api/v2/search?id=&t=search', $entry);
    }

    public function test_legacy_audit_job_persists_queued_requests_and_coalesces_user_updates(): void
    {
        config(['nntmux.api.access_update_interval' => 60]);

        (new RecordApiUsage(1, '/api/v2/search?id=one', '192.0.2.1', '2026-07-14 10:00:00'))->handle();
        (new RecordApiUsage(1, '/api/v2/search?id=two', '192.0.2.2', '2026-07-14 10:00:01'))->handle();

        $this->assertSame(2, DB::table('user_requests')->count());
        $this->assertSame('192.0.2.1', DB::table('users')->where('id', 1)->value('host'));
    }
}
