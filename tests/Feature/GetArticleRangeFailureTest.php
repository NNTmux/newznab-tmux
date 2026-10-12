<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\GetArticleRange;
use App\Services\Binaries\MissedPartHandler;
use App\Services\NNTP\NNTPService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
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
            $table->unsignedBigInteger('first_record')->default(1001);
            $table->unsignedBigInteger('last_record')->default(1000);
        });
        Schema::create('missed_parts', function (Blueprint $table): void {
            $table->unsignedBigInteger('numberid');
            $table->unsignedInteger('groups_id');
            $table->unsignedInteger('attempts')->default(0);
            $table->unique(['numberid', 'groups_id']);
        });
        DB::table('usenet_groups')->insert(['id' => 7, 'name' => 'alt.binaries.test']);
    }

    #[DataProvider('pausedRanges')]
    public function test_launched_range_paused_by_capacity_remains_repairable_after_sibling_cursor_progress(
        string $mode, int $first, int $last, string $cursor, int $siblingPosition
    ): void {
        $this->fillCapacity();
        $command = $this->refuseNntp();
        $this->artisan('articles:get-range', ['mode' => $mode, 'group' => 'alt.binaries.test', 'first' => $first, 'last' => $last])
            ->assertSuccessful();
        $this->assertFalse($command->contacted);
        DB::table('usenet_groups')->where('id', 7)->update([$cursor => $siblingPosition]);

        $repair = (new MissedPartHandler)->getMissingParts(7);
        $this->assertCount(500, $repair);
        $this->assertSame($first, (int) $repair[0]->numberid);
        $this->assertSame($last, (int) $repair[499]->numberid);
        $this->assertSame(1, (int) $repair[0]->attempts);
        $this->assertSame($siblingPosition, (int) DB::table('usenet_groups')->where('id', 7)->value($cursor));
    }

    /** @return array<string, array{string, int, int, string, int}> */
    public static function pausedRanges(): array
    {
        return ['forward' => ['binaries', 1001, 1500, 'last_record', 2000],
            'backfill' => ['backfill', 501, 1000, 'first_record', 1]];
    }

    #[DataProvider('invalidRanges')]
    public function test_invalid_range_or_mode_cannot_enqueue_articles_even_when_capacity_is_full(string $mode, string $first, string $last): void
    {
        $this->fillCapacity();
        $command = $this->refuseNntp();
        $this->artisan('articles:get-range', ['mode' => $mode, 'group' => 'alt.binaries.test', 'first' => $first, 'last' => $last])
            ->assertFailed();
        $this->assertFalse($command->contacted);
        $this->assertSame(0, DB::table('missed_parts')->count());
        $this->assertSame(1000, (int) DB::table('usenet_groups')->value('last_record'));
    }

    /** @return array<string, array{string, string, string}> */
    public static function invalidRanges(): array
    {
        return ['invalid mode' => ['invalid', '1', '50'], 'reversed' => ['binaries', '50', '1'],
            'non numeric' => ['binaries', 'invalid', '50'], 'zero' => ['backfill', '0', '50']];
    }

    public function test_capacity_deferral_insert_failure_reports_failure_without_nntp_or_cursor_changes(): void
    {
        $this->fillCapacity();
        DB::statement("CREATE TRIGGER reject_repair BEFORE INSERT ON missed_parts BEGIN SELECT RAISE(ABORT, 'repair storage unavailable'); END");
        $command = $this->refuseNntp();

        $this->artisan('articles:get-range', ['mode' => 'binaries', 'group' => 'alt.binaries.test', 'first' => 1001, 'last' => 1500])
            ->assertFailed();

        $this->assertFalse($command->contacted);
        $this->assertSame(0, DB::table('missed_parts')->count());
        $this->assertSame(1000, (int) DB::table('usenet_groups')->value('last_record'));
    }

    public function test_capacity_deferral_for_unknown_group_cannot_report_a_durable_queue(): void
    {
        $this->fillCapacity();
        $command = $this->refuseNntp();

        $this->artisan('articles:get-range', ['mode' => 'backfill', 'group' => 'alt.binaries.missing', 'first' => 501, 'last' => 1000])
            ->assertFailed();

        $this->assertFalse($command->contacted);
        $this->assertSame(0, DB::table('missed_parts')->count());
        $this->assertSame(1001, (int) DB::table('usenet_groups')->value('first_record'));
    }

    private function fillCapacity(): void
    {
        config(['release_verification.required' => true, 'release_verification.pending_bytes' => 100]);
        Schema::create('release_verification_state', function (Blueprint $table): void {
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('pending_bytes');
        });
        DB::table('release_verification_state')->insert(['id' => 1, 'pending_bytes' => 100]);
    }

    private function refuseNntp(): GetArticleRange
    {
        $command = new class extends GetArticleRange
        {
            public bool $contacted = false;

            protected function getNntp(): NNTPService
            {
                $this->contacted = true;
                throw new \LogicException('A capacity-paused or invalid range must not contact NNTP.');
            }
        };
        $this->app->instance(GetArticleRange::class, $command);

        return $command;
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
