<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\GetArticleRange;
use App\Services\NNTP\NNTPService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class GetArticleRangeFailureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        Schema::create('usenet_groups', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->string('name');
        });
        Schema::create('missed_parts', function (Blueprint $table): void {
            $table->unsignedBigInteger('numberid');
            $table->unsignedInteger('groups_id');
            $table->unsignedInteger('attempts')->default(0);
            $table->unique(['numberid', 'groups_id']);
        });
        DB::table('usenet_groups')->insert(['id' => 7, 'name' => 'alt.binaries.test']);
    }

    /**
     * Ranges run in parallel and every successful one advances the group cursor to its own end, so a
     * range whose worker could not log in would otherwise never be fetched (it was not in missed_parts).
     */
    public function test_a_range_that_cannot_connect_is_queued_for_part_repair(): void
    {
        $this->app->bind(GetArticleRange::class, fn (): GetArticleRange => new class extends GetArticleRange
        {
            protected function getNntp(): NNTPService
            {
                throw new \Exception('Unable to connect to usenet. Error: Cannot authenticate to server news.example.test (ssl) (Authentication rejected)');
            }
        });

        $this->artisan('articles:get-range', ['mode' => 'binaries', 'group' => 'alt.binaries.test', 'first' => 1001, 'last' => 1500])
            ->assertFailed();

        $this->assertSame(500, DB::table('missed_parts')->where('groups_id', 7)->count());
        $this->assertSame(1001, (int) DB::table('missed_parts')->min('numberid'));
        $this->assertSame(1500, (int) DB::table('missed_parts')->max('numberid'));
        $this->assertSame(1, (int) DB::table('missed_parts')->value('attempts'));
    }

    public function test_an_unknown_group_queues_nothing(): void
    {
        $this->app->bind(GetArticleRange::class, fn (): GetArticleRange => new class extends GetArticleRange
        {
            protected function getNntp(): NNTPService
            {
                throw new \Exception('Unable to connect to usenet.');
            }
        });

        $this->artisan('articles:get-range', ['mode' => 'binaries', 'group' => 'alt.binaries.missing', 'first' => 1, 'last' => 50])
            ->assertFailed();

        $this->assertSame(0, DB::table('missed_parts')->count());
    }
}
