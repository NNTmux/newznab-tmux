<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\BlacklistService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlacklistUsageStampTest extends TestCase
{
    private int $updates = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        DB::reconnect();

        DB::statement('CREATE TABLE binaryblacklist (id INTEGER PRIMARY KEY, last_activity DATE NULL)');
        DB::table('binaryblacklist')->insert([['id' => 1], ['id' => 2], ['id' => 3]]);
        DB::listen(function ($query): void {
            if (str_starts_with($query->sql, 'UPDATE binaryblacklist')) {
                $this->updates++;
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_matched_rules_are_stamped_with_today(): void
    {
        Carbon::setTestNow('2026-03-04 10:00:00');

        (new BlacklistService)->updateBlacklistUsage([1, '2']);

        $this->assertSame(
            [1 => '2026-03-04', 2 => '2026-03-04', 3 => null],
            DB::table('binaryblacklist')->orderBy('id')->pluck('last_activity', 'id')->all()
        );
    }

    public function test_a_rule_already_stamped_today_is_not_written_again(): void
    {
        Carbon::setTestNow('2026-03-04 10:00:00');
        $service = new BlacklistService;

        $service->updateBlacklistUsage([1]);
        $service->updateBlacklistUsage([1]);
        $service->updateBlacklistUsage([1, 2]);

        $this->assertSame(2, $this->updates);
        $this->assertSame('2026-03-04', DB::table('binaryblacklist')->where('id', 2)->value('last_activity'));
    }

    public function test_a_rule_is_stamped_again_on_the_next_day(): void
    {
        Carbon::setTestNow('2026-03-04 23:59:00');
        $service = new BlacklistService;
        $service->updateBlacklistUsage([1]);

        Carbon::setTestNow('2026-03-05 00:01:00');
        $service->updateBlacklistUsage([1]);

        $this->assertSame(2, $this->updates);
        $this->assertSame('2026-03-05', DB::table('binaryblacklist')->where('id', 1)->value('last_activity'));
    }

    public function test_no_statement_runs_without_matched_rules(): void
    {
        (new BlacklistService)->updateBlacklistUsage([]);

        $this->assertSame(0, $this->updates);
    }
}
