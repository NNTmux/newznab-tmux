<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Par2RenameOutcome;
use App\Models\Release;
use App\Services\NameFixing\NameFixingService;
use App\Services\NNTP\NNTPService;
use App\Services\Nzb\NzbContentsService;
use App\Services\Nzb\NzbParserService;
use DariusIII\NetNntp\Error;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use SimpleXMLElement;
use Tests\Support\Par2RenameTestDatabase;
use Tests\TestCase;

class Par2RenameBatchTest extends TestCase
{
    use Par2RenameTestDatabase;

    /** @var list<string> */
    private array $visited = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'tmux.fix_names.cache_store' => 'array',
            'tmux.fix_names.par2_max_releases' => 2,
            'tmux.fix_names.par2_budget_seconds' => 30,
        ]);
        Cache::store('array')->flush();
        $this->createPar2RenameSchema();
    }

    public function test_a_download_pass_is_bounded_and_resumes_after_the_last_release(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->pendingPar2Release(['guid' => 'guid-'.$i]);
        }
        $this->bindNzb();
        $this->runBatch();
        $this->assertSame(['guid-1', 'guid-2'], $this->visited);
        $this->assertSame(2, Release::query()->where('proc_par2', 1)->count());
        $this->runBatch();
        $this->assertSame(['guid-1', 'guid-2', 'guid-3', 'guid-4'], $this->visited);
        $this->runBatch();
        $this->assertSame(5, Release::query()->where('proc_par2', 1)->count());
    }

    public function test_retryable_old_releases_do_not_starve_later_work_and_are_revisited(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->pendingPar2Release(['guid' => 'guid-'.$i]);
        }
        $this->bindNzb(['guid-1', 'guid-2']);
        $this->runBatch();
        $this->assertSame(['guid-1', 'guid-2'], $this->visited);
        $this->runBatch();
        $this->assertSame(['guid-1', 'guid-2', 'guid-3'], $this->visited);
        $this->runBatch();
        $this->assertSame(['guid-1', 'guid-2', 'guid-3', 'guid-1', 'guid-2'], $this->visited);
        $this->assertSame(2, Release::query()->where('proc_par2', 0)->count());
    }

    public function test_new_arrivals_do_not_extend_the_current_sweep_and_starve_retries(): void
    {
        $this->pendingPar2Release(['guid' => 'old-1']);
        $this->pendingPar2Release(['guid' => 'old-2']);
        $this->bindNzb(['old-1', 'old-2']);
        $this->runBatch();
        $this->pendingPar2Release(['guid' => 'new-3']);
        $this->runBatch();
        $this->assertSame(['old-1', 'old-2', 'old-1', 'old-2'], $this->visited);
    }

    public function test_grouped_retryable_result_does_not_count_as_a_rename_or_complete_the_check(): void
    {
        $release = $this->pendingPar2Release([
            'leftguid' => 'a', 'proc_nfo' => 1, 'proc_files' => 1, 'proc_uid' => 1,
            'proc_srr' => 1, 'proc_hash16k' => 1, 'proc_crc32' => 1,
        ]);
        $stats = (new NameFixingService)->processStandardBatch('a', 100, false,
            static fn (object $release): Par2RenameOutcome => Par2RenameOutcome::Retryable);
        $this->assertSame(['checked' => 1, 'fixed' => 0], $stats);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_grouped_retryable_releases_rotate_to_older_work_and_wrap_across_processes(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->pendingPar2Release([
                'leftguid' => 'a', 'proc_nfo' => 1, 'proc_files' => 1, 'proc_uid' => 1,
                'proc_srr' => 1, 'proc_hash16k' => 1, 'proc_crc32' => 1,
            ])->id;
        }
        $visited = [];
        $processor = static function (object $release) use (&$visited): Par2RenameOutcome {
            $visited[] = (int) $release->releases_id;

            return Par2RenameOutcome::Retryable;
        };
        for ($i = 0; $i < 3; $i++) {
            (new NameFixingService)->processStandardBatch('a', 2, false, $processor);
        }
        $this->assertSame([$ids[2], $ids[1], $ids[0], $ids[2], $ids[1]], $visited);
        $this->assertSame(3, Release::query()->where('proc_par2', 0)->count());
    }

    public function test_sweep_cursor_survives_a_long_historical_cohort(): void
    {
        $this->freezeTime();
        for ($i = 1; $i <= 3; $i++) {
            $this->pendingPar2Release(['guid' => 'guid-'.$i]);
        }
        $this->bindNzb(['guid-1', 'guid-2']);
        $this->runBatch();
        $this->travel(8)->days();
        $this->runBatch();
        $this->assertSame(['guid-1', 'guid-2', 'guid-3'], $this->visited);
    }

    public function test_grouped_reservation_claim_defers_an_overlapping_selector(): void
    {
        $release = $this->pendingPar2Release(['leftguid' => 'a']);
        $lock = Cache::store('array')->lock('rename:standard:cursor:a:lock', 120);
        $this->assertTrue($lock->get());
        try {
            $result = (new NameFixingService)->processStandardBatch('a', 1, false,
                static fn (): never => throw new RuntimeException('Must not process claimed work'));
            $this->assertSame(['checked' => 0, 'fixed' => 0], $result);
            $this->assertSame(0, (int) $release->fresh()->proc_par2);
        } finally {
            $lock->release();
        }
    }

    public function test_interrupted_grouped_work_does_not_repeat_the_newest_slice_forever(): void
    {
        $older = $this->pendingPar2Release([
            'leftguid' => 'a', 'proc_nfo' => 1, 'proc_files' => 1, 'proc_uid' => 1,
            'proc_srr' => 1, 'proc_hash16k' => 1, 'proc_crc32' => 1,
        ]);
        $newer = $this->pendingPar2Release([
            'leftguid' => 'a', 'proc_nfo' => 1, 'proc_files' => 1, 'proc_uid' => 1,
            'proc_srr' => 1, 'proc_hash16k' => 1, 'proc_crc32' => 1,
        ]);
        try {
            (new NameFixingService)->processStandardBatch('a', 1, false,
                static fn (): never => throw new RuntimeException('Worker interrupted'));
            $this->fail('Expected interrupted processing.');
        } catch (RuntimeException $e) {
            $this->assertSame('Worker interrupted', $e->getMessage());
        }
        $visited = [];
        $processor = static function (object $release) use (&$visited): Par2RenameOutcome {
            $visited[] = (int) $release->releases_id;

            return Par2RenameOutcome::Retryable;
        };
        (new NameFixingService)->processStandardBatch('a', 1, false, $processor);
        (new NameFixingService)->processStandardBatch('a', 1, false, $processor);
        $this->assertSame([$older->id, $newer->id], $visited);
        $this->assertSame(2, Release::query()->where('proc_par2', 0)->count());
    }

    public function test_time_budget_yields_and_the_next_batch_resumes(): void
    {
        $this->freezeTime();
        for ($i = 1; $i <= 3; $i++) {
            $this->pendingPar2Release(['guid' => 'guid-'.$i]);
        }
        $this->bindNzb(onLoad: function (string $guid): void {
            if ($guid === 'guid-1') {
                $this->travel(31)->seconds();
            }
        });
        $this->runBatch();
        $this->assertSame(['guid-1'], $this->visited);
        $this->runBatch();
        $this->assertSame(['guid-1', 'guid-2', 'guid-3'], $this->visited);
    }

    public function test_a_cache_outage_stops_the_batch_with_an_error_and_preserves_work(): void
    {
        $release = $this->pendingPar2Release();
        Cache::shouldReceive('store')->with('array')->andThrow(new RuntimeException('cache unavailable'));
        try {
            $this->runBatch();
            $this->fail('The batch must report a failed coordination backend.');
        } catch (RuntimeException $e) {
            $this->assertSame('cache unavailable', $e->getMessage());
        }
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
    }

    public function test_grouped_connection_failure_leaves_par2_pending(): void
    {
        $release = $this->pendingPar2Release([
            'leftguid' => 'a', 'proc_nfo' => 1, 'proc_files' => 1, 'proc_uid' => 1,
            'proc_srr' => 1, 'proc_hash16k' => 1, 'proc_crc32' => 1,
        ]);
        $this->app->instance(NNTPService::class, new class('Connection limit reached') extends NNTPService
        {
            public function __construct(private string $connectionError) {}

            public function doConnect(bool $compression = true, bool $alternate = false): mixed
            {
                return new Error($this->connectionError, 502);
            }

            public function doQuit(bool $force = false): mixed
            {
                return true;
            }
        });
        Artisan::call('releases:fix-names-group', ['type' => 'standard', '--guid-char' => 'a', '--limit' => 1]);
        $this->assertSame(0, (int) $release->fresh()->proc_par2);
        $this->assertStringContainsString('Fixed 0 release names', Artisan::output());
    }

    /** @param list<string> $unavailable */
    private function bindNzb(array $unavailable = [], ?\Closure $onLoad = null): void
    {
        $load = function (string $guid) use ($unavailable, $onLoad): SimpleXMLElement|false {
            $this->visited[] = $guid;
            if ($onLoad !== null) {
                $onLoad($guid);
            }

            return in_array($guid, $unavailable, true) ? false : new SimpleXMLElement('<nzb><file subject="movie.mkv yEnc (1/10)"><segments><segment bytes="1000" number="1">video-id</segment></segments></file></nzb>');
        };
        $this->app->instance(NzbContentsService::class, new class($load) extends NzbContentsService
        {
            public function __construct(private \Closure $load)
            {
                $this->parserService = new NzbParserService;
            }

            public function loadNzb(string $guid): SimpleXMLElement|false
            {
                return ($this->load)($guid);
            }
        });
    }

    private function runBatch(): void
    {
        (new NameFixingService)->fixNamesWithPar2(2, true, 1, true, false,
            $this->createStub(NNTPService::class));
    }
}
