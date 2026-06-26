<?php

namespace Tests\Feature;

use App\Http\Controllers\CartController;
use App\Models\Release;
use App\Models\User;
use App\Models\UsersRelease;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CartQueryCountTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        Schema::create('settings', static function (Blueprint $table): void {
            $table->string('section')->nullable();
            $table->string('subsection')->nullable();
            $table->string('name')->primary();
            $table->text('value')->nullable();
            $table->text('hint')->nullable();
            $table->text('setting')->nullable();
        });

        Schema::create('releases', static function (Blueprint $table): void {
            $table->id();
            $table->string('guid')->unique();
        });

        Schema::create('users_releases', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('users_id');
            $table->unsignedInteger('releases_id');
            $table->timestamps();
            $table->unique(['users_id', 'releases_id']);
        });
    }

    public function test_store_batches_cart_add_queries_and_scopes_existing_rows_to_current_user(): void
    {
        $user = new User;
        $user->forceFill(['id' => 1]);

        $otherUserId = 2;
        $releaseIdsByGuid = $this->createReleases(12);
        $guids = array_keys($releaseIdsByGuid);

        UsersRelease::addCart($user->id, $releaseIdsByGuid[$guids[0]]);
        UsersRelease::addCart($otherUserId, $releaseIdsByGuid[$guids[1]]);

        $controller = new CartController;
        $controller->userdata = $user;

        $request = Request::create('/cart/add', 'POST', ['id' => implode(',', $guids)]);
        $request->headers->set('Accept', 'application/json');

        $counts = $this->countQueriesPerTable(function () use ($controller, $request, &$response): void {
            $response = $controller->store($request);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(12, $response->getData(true)['cartCount']);
        $this->assertSame(12, UsersRelease::query()->where('users_id', $user->id)->count());
        $this->assertSame(1, UsersRelease::query()->where('users_id', $otherUserId)->count());

        $this->assertLessThanOrEqual(1, $counts['releases'] ?? 0);
        $this->assertLessThanOrEqual(3, $counts['users_releases'] ?? 0);
    }

    public function test_delete_by_guid_uses_one_set_based_delete_for_multiple_cart_items(): void
    {
        $userId = 1;
        $otherUserId = 2;
        $releaseIdsByGuid = $this->createReleases(5);
        $guids = array_keys($releaseIdsByGuid);

        foreach ($releaseIdsByGuid as $releaseId) {
            UsersRelease::addCart($userId, $releaseId);
            UsersRelease::addCart($otherUserId, $releaseId);
        }

        $counts = $this->countQueriesPerTable(function () use ($guids, $userId, &$deleted): void {
            $deleted = UsersRelease::delCartByGuid($guids, $userId);
        });

        $this->assertTrue($deleted);
        $this->assertSame(0, UsersRelease::query()->where('users_id', $userId)->count());
        $this->assertSame(5, UsersRelease::query()->where('users_id', $otherUserId)->count());
        $this->assertSame(1, array_sum($counts));
        $this->assertSame(1, $counts['users_releases'] ?? 0);
    }

    /**
     * @return array<string, int>
     */
    private function createReleases(int $count): array
    {
        $releaseIdsByGuid = [];

        for ($i = 1; $i <= $count; $i++) {
            $guid = 'cart-query-count-guid-'.$i;
            $release = Release::query()->create(['guid' => $guid]);
            $releaseIdsByGuid[$guid] = (int) $release->id;
        }

        return $releaseIdsByGuid;
    }

    /**
     * @return array<string, int>
     */
    private function countQueriesPerTable(\Closure $callable): array
    {
        $counts = [];
        $listener = static function (QueryExecuted $event) use (&$counts): void {
            if (preg_match('/\b(?:from|into|update|delete\s+from)\s+["`]?([a-zA-Z_][a-zA-Z0-9_]*)["`]?/i', $event->sql, $m)) {
                $table = strtolower($m[1]);
                $counts[$table] = ($counts[$table] ?? 0) + 1;
            }
        };

        DB::listen($listener);
        $callable();

        return $counts;
    }
}
