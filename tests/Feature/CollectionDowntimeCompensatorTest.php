<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Tmux\CollectionDowntimeCompensator;
use App\Services\Tmux\CollectionReconciliationBarrier;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\MarkerIoFailures;
use Tests\TestCase;

require_once __DIR__.'/../Support/MarkerIoFailures.php';

final class CollectionDowntimeCompensatorTest extends TestCase
{
    private string $marker = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->marker = sys_get_temp_dir().'/nntmux-last-activity-'.uniqid('', true).'/marker';
        Schema::dropIfExists('collections');
        Schema::create('collections', static function (Blueprint $table): void {
            $table->increments('id');
            $table->dateTime('dateadded')->nullable();
            $table->integer('filecheck')->default(0);
        });
        $this->travelTo(Carbon::parse('2026-10-11 12:00:00'));
    }

    protected function tearDown(): void
    {
        MarkerIoFailures::$shortWrite = false;
        MarkerIoFailures::$renameFails = false;
        MarkerIoFailures::$directorySyncFails = false;
        MarkerIoFailures::$directorySyncCalls = 0;
        MarkerIoFailures::$directorySyncFailFromCall = null;
        MarkerIoFailures::$checkpointSyncFails = false;
        MarkerIoFailures::$syncFailureUri = null;
        Schema::dropIfExists('collections');
        @unlink($this->marker);
        @unlink($this->marker.'.lock');
        @unlink($this->marker.'.recovery');
        @rmdir(\dirname($this->marker));

        parent::tearDown();
    }

    public function test_a_long_outage_extends_only_collections_that_were_still_waiting(): void
    {
        // The indexer stopped at 11:00 and starts again at 12:00, with a 2 hour delay.
        $this->lastActive(Carbon::parse('2026-10-11 11:00:00')->getTimestamp());
        $waiting = $this->collection('2026-10-11 10:30:00');   // 30 min into its 2 h wait at 11:00
        $expired = $this->collection('2026-10-11 08:30:00');   // already past its 2 h wait at 11:00
        $newer = $this->collection('2026-10-11 11:50:00');     // written after the last recorded activity

        $moved = $this->compensator()->compensate(2);

        $this->assertSame(2, $moved);
        $this->assertSame('2026-10-11 12:00:00', $this->dateAdded($newer), 'Gets back only the 10 minutes it waited.');
        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting), 'Pushed back by the hour of downtime.');
        $this->assertSame('2026-10-11 08:30:00', $this->dateAdded($expired), 'Already due when the indexer stopped.');
    }

    public function test_a_short_restart_changes_nothing(): void
    {
        $this->lastActive(Carbon::parse('2026-10-11 11:58:00')->getTimestamp());
        $waiting = $this->collection('2026-10-11 11:00:00');

        $this->assertSame(0, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:00:00', $this->dateAdded($waiting));
    }

    public function test_a_long_outage_restores_the_full_downtime(): void
    {
        $this->lastActive(Carbon::parse('2026-10-11 02:00:00')->getTimestamp());
        $waiting = $this->collection('2026-10-11 01:30:00');

        $this->compensator()->compensate(2);

        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting), 'Ten hours down: resumes 30 minutes into its wait, not stale.');
    }

    public function test_unknown_downtime_changes_nothing_and_starts_recording(): void
    {
        $waiting = $this->collection('2026-10-11 09:00:00');

        $this->assertSame(0, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 09:00:00', $this->dateAdded($waiting));
        $this->assertSame(Carbon::now()->getTimestamp(), $this->compensator()->lastActivity());
    }

    public function test_a_second_start_right_after_compensating_changes_nothing(): void
    {
        $this->lastActive(Carbon::parse('2026-10-11 11:00:00')->getTimestamp());
        $waiting = $this->collection('2026-10-11 10:30:00');
        $compensator = $this->compensator();

        $compensator->compensate(2);
        $this->travel(30)->seconds();
        $compensator->compensate(2);

        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting), 'tmux:start and tmux:monitor both compensate on start; only the first may move it.');
    }

    public function test_record_activity_stores_the_current_time(): void
    {
        $this->compensator()->recordActivity();

        $this->assertSame(Carbon::now()->getTimestamp(), $this->compensator()->lastActivity());
    }

    public function test_a_short_marker_write_preserves_the_outage_and_leaves_collections_untouched(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive($previous);
        $waiting = $this->collection('2026-10-11 10:30:00');
        MarkerIoFailures::$shortWrite = true;

        try {
            $this->compensator()->compensate(2);
            $this->fail('A partial marker write must abort compensation.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unable to write', $exception->getMessage());
        } finally {
            MarkerIoFailures::$shortWrite = false;
        }

        $this->assertSame($previous, $this->compensator()->lastActivity());
        $this->assertSame('2026-10-11 10:30:00', $this->dateAdded($waiting));
        $this->assertSame(1, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
    }

    public function test_a_failed_marker_replacement_preserves_the_previous_timestamp(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive($previous);
        MarkerIoFailures::$renameFails = true;

        try {
            $this->compensator()->recordActivity();
            $this->fail('A failed replacement must abort the activity update.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unable to replace', $exception->getMessage());
        } finally {
            MarkerIoFailures::$renameFails = false;
        }

        $this->assertSame($previous, $this->compensator()->lastActivity());
        $this->assertSame([], glob(\dirname($this->marker).'/activity-*'));
    }

    public function test_work_follows_the_waiting_window_not_the_id_range(): void
    {
        $this->lastActive(Carbon::parse('2026-10-11 11:00:00')->getTimestamp());
        DB::table('collections')->insert(['id' => 2_000_000_001, 'dateadded' => '2026-10-11 10:30:00']);
        $updates = 0;
        DB::listen(static function ($query) use (&$updates): void {
            $updates += preg_match('/^\s*update\b/i', $query->sql);
        });

        $this->assertSame(1, $this->compensator()->compensate(2));
        $this->assertSame(1, $updates, 'Only the occupied window, however high the ids or long the outage.');
    }

    public function test_the_newest_waiting_lookup_skips_completed_rows_and_id_order(): void
    {
        $this->lastActive(Carbon::parse('2026-10-11 11:00:00')->getTimestamp());
        $newest = $this->collection('2026-10-11 10:50:00');
        $older = $this->collection('2026-10-11 10:20:00');
        $completed = DB::table('collections')->insertGetId(['dateadded' => '2026-10-11 11:50:00', 'filecheck' => 4]);

        $this->assertSame(2, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:50:00', $this->dateAdded($newest));
        $this->assertSame('2026-10-11 11:20:00', $this->dateAdded($older));
        $this->assertSame('2026-10-11 11:50:00', $this->dateAdded($completed));
    }

    public function test_rows_crossing_into_a_later_window_are_shifted_once(): void
    {
        $this->lastActive(Carbon::parse('2026-10-11 11:00:00')->getTimestamp());
        $early = $this->collection('2026-10-11 09:00:30');
        $late = $this->collection('2026-10-11 10:59:30');

        // Down 10 minutes: each moves 10 minutes, landing in windows already processed.
        $this->travelTo(Carbon::parse('2026-10-11 11:10:00'));
        $this->compensator()->compensate(2);

        $this->assertSame('2026-10-11 09:10:30', $this->dateAdded($early));
        $this->assertSame('2026-10-11 11:09:30', $this->dateAdded($late));
    }

    public function test_the_marker_survives_a_cache_flush(): void
    {
        $this->compensator()->recordActivity();
        Cache::flush();

        $this->assertSame(Carbon::now()->getTimestamp(), $this->compensator()->lastActivity());
    }

    public function test_an_interrupted_start_never_shifts_rows_twice(): void
    {
        $this->lastActive(Carbon::parse('2026-10-11 11:00:00')->getTimestamp());
        $waiting = $this->collection('2026-10-11 10:30:00');
        $this->compensator()->compensate(2);

        // A retry a minute later (the first start died after its updates).
        $this->travel(1)->minutes();
        $this->compensator()->compensate(2);

        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
    }

    public function test_works_in_a_timezone_west_of_utc(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $this->travelTo(Carbon::parse('2026-10-11 12:00:00'));
            $this->lastActive(Carbon::parse('2026-10-11 11:00:00')->getTimestamp());
            $waiting = $this->collection('2026-10-11 10:30:00');

            $this->assertSame(1, $this->compensator()->compensate(2));
            $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_a_dst_fall_back_never_moves_rows_backwards(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            // 01:50 EDT to 01:10 EST on 2026-11-01 is 20 minutes, though the clock reads earlier.
            $lastActive = Carbon::parse('2026-11-01 05:50:00', 'UTC')->getTimestamp();
            Carbon::setTestNow(Carbon::createFromTimestamp($lastActive + 1200, 'UTC'));
            $this->lastActive($lastActive);
            $waiting = $this->collection('2026-11-01 01:30:00');
            $ambiguous = $this->collection('2026-11-01 01:45:00'); // 01:45 EDT plus 20 minutes reads 01:05 EST

            $this->compensator()->compensate(2);

            $this->assertSame('2026-11-01 01:50:00', $this->dateAdded($waiting), 'Moved forward by the 20 real minutes.');
            $this->assertSame('2026-11-01 01:05:00', $this->dateAdded($ambiguous), 'Moved once, though its wall clock reads earlier.');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_a_dst_spring_forward_keeps_the_real_elapsed_wait(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            // 01:50 EST to 03:10 EDT on 2026-03-08 is 20 real minutes.
            $lastActive = Carbon::parse('2026-03-08 06:50:00', 'UTC')->getTimestamp();
            Carbon::setTestNow(Carbon::createFromTimestamp($lastActive + 1200, 'UTC'));
            $this->lastActive($lastActive);
            $waiting = $this->collection('2026-03-08 01:45:00');

            $this->compensator()->compensate(2);

            $this->assertSame('2026-03-08 03:05:00', $this->dateAdded($waiting), '01:45 EST plus 20 minutes is 03:05 EDT.');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_a_three_hour_fall_back_resolves_the_occurrence_before_restart(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Antarctica/Casey');
        try {
            $this->travelTo(Carbon::parse('2022-03-12 13:10:00', 'UTC'));
            $this->lastActive(Carbon::parse('2022-03-12 12:40:00', 'UTC')->getTimestamp());
            $waiting = $this->collection('2022-03-12 23:10:00');

            $this->assertSame(1, $this->compensator()->compensate(2));
            $this->assertSame('2022-03-12 23:40:00', $this->dateAdded($waiting));
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_collections_from_the_repeated_fall_back_hour_are_included(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            // Shut down 02:50 EST, restart 03:20 EST on 2026-11-01; added at 01:10 EST.
            $lastActive = Carbon::parse('2026-11-01 07:50:00', 'UTC')->getTimestamp();
            Carbon::setTestNow(Carbon::createFromTimestamp($lastActive + 1800, 'UTC'));
            $this->lastActive($lastActive);
            $repeatedHour = $this->collection('2026-11-01 01:10:00');
            $lateInRepeatedHour = $this->collection('2026-11-01 01:45:00');
            $alreadyExpired = $this->collection('2026-11-01 00:55:00'); // 2h55 old at the 02:50 EST shutdown

            $this->compensator()->compensate(2);

            $this->assertSame('2026-11-01 01:40:00', $this->dateAdded($repeatedHour), 'Gets the 30 minutes back.');
            $this->assertSame('2026-11-01 02:15:00', $this->dateAdded($lateInRepeatedHour), 'Read as 01:45 EST, the occurrence before the shutdown.');
            $this->assertSame('2026-11-01 00:55:00', $this->dateAdded($alreadyExpired), 'Inside the widened bound, but already expired.');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_ingestion_notes_are_throttled_but_keep_the_marker_current(): void
    {
        $this->lastActive(Carbon::now()->subMinutes(10)->getTimestamp());
        touch($this->marker, time() - 600);

        $this->compensator()->noteIngestion();
        $this->assertSame(Carbon::now()->getTimestamp(), $this->compensator()->lastActivity(), 'A stale marker is refreshed.');

        file_put_contents($this->marker, '123');
        $this->compensator()->noteIngestion();
        $this->assertSame(123, $this->compensator()->lastActivity(), 'Written seconds ago: left alone.');
    }

    public function test_resumed_worker_activity_compensates_before_refreshing_the_marker(): void
    {
        $this->lastActive(Carbon::now('UTC')->subHour()->getTimestamp());
        touch($this->marker, time() - 600);
        $waiting = $this->collection('2026-10-11 10:30:00');

        $this->compensator()->noteIngestion();

        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
        $this->assertSame(Carbon::now('UTC')->getTimestamp(), $this->compensator()->lastActivity());
        $this->assertSame(0, $this->compensator()->compensate(2), 'A subsequent tmux start must not compensate twice.');
    }

    public function test_reconciliation_barrier_compensates_before_consuming_collection_dates(): void
    {
        $this->lastActive(Carbon::now('UTC')->subHour()->getTimestamp());
        $waiting = $this->collection('2026-10-11 10:30:00');
        $service = $this->compensator();

        $observed = $service->withReconciliationBarrier(2, fn (): string => $this->dateAdded($waiting));

        $this->assertSame('2026-10-11 11:30:00', $observed);
        $this->assertSame(0, $service->compensate(2));
    }

    public function test_barrier_resolution_uses_the_isolated_concrete_test_marker(): void
    {
        $this->assertSame(
            $this->app->make(CollectionDowntimeCompensator::class),
            $this->app->make(CollectionReconciliationBarrier::class),
        );
    }

    public function test_parent_sync_failure_prevents_first_marker_publication(): void
    {
        $parent = \dirname(\dirname($this->marker));
        MarkerIoFailures::$syncFailureUri = $parent;
        try {
            $this->compensator()->recordActivity();
            $this->fail('A newly created marker directory needs a durable parent entry.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unable to sync marker parent', $exception->getMessage());
        } finally {
            MarkerIoFailures::$syncFailureUri = null;
        }

        $this->assertNull($this->compensator()->lastActivity());
        $this->compensator()->recordActivity();
        $this->assertSame(Carbon::now('UTC')->getTimestamp(), $this->compensator()->lastActivity());
    }

    #[DataProvider('compensationWindows')]
    public function test_only_delay_waiting_statuses_receive_compensation(string $timezone, string $stopped, string $restarted, string $added, string $shifted): void
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set($timezone);
        try {
            $this->travelTo(Carbon::parse($restarted, 'UTC'));
            $this->lastActive(Carbon::parse($stopped, 'UTC')->getTimestamp());
            $waiting = [];
            foreach ([0, 1, 10, 15, 16] as $status) {
                $waiting[] = DB::table('collections')->insertGetId(['dateadded' => $added, 'filecheck' => $status]);
            }
            $finished = [];
            foreach ([2, 3, 4, 5] as $status) {
                $finished[] = DB::table('collections')->insertGetId(['dateadded' => $added, 'filecheck' => $status]);
            }

            $this->assertSame(5, $this->compensator()->compensate(2));
            foreach ($waiting as $id) {
                $this->assertSame($shifted, $this->dateAdded($id));
            }
            foreach ($finished as $id) {
                $this->assertSame($added, $this->dateAdded($id), 'Completed data must keep its retention age.');
            }
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    /** @return array<string, array{string, string, string, string, string}> */
    public static function compensationWindows(): array
    {
        return [
            'UTC window' => ['UTC', '2026-10-11 11:00:00', '2026-10-11 12:00:00', '2026-10-11 10:30:00', '2026-10-11 11:30:00'],
            'DST keyset' => ['America/New_York', '2026-03-08 06:50:00', '2026-03-08 07:10:00', '2026-03-08 01:45:00', '2026-03-08 03:05:00'],
        ];
    }

    public function test_rows_inserted_during_a_dst_scan_are_not_compensated(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            $this->travelTo(Carbon::parse('2026-11-01 06:10:00', 'UTC'));
            $this->lastActive(Carbon::parse('2026-11-01 05:50:00', 'UTC')->getTimestamp());
            foreach (array_chunk(array_fill(0, 5000, ['dateadded' => '2026-11-01 01:45:00', 'filecheck' => 0]), 500) as $chunk) {
                DB::table('collections')->insert($chunk);
            }
            $lateId = null;
            DB::listen(static function ($query) use (&$lateId): void {
                if ($lateId === null && str_starts_with($query->sql, 'select "id", "dateadded"')) {
                    $lateId = DB::table('collections')->insertGetId(['dateadded' => '2026-11-01 01:11:00', 'filecheck' => 0]);
                }
            });

            $this->assertSame(5000, $this->compensator()->compensate(2));
            $this->assertNotNull($lateId);
            $this->assertSame('2026-11-01 01:11:00', $this->dateAdded($lateId), 'A row inserted after the snapshot keeps its original age.');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function test_an_unsynced_directory_aborts_before_collections_move(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive($previous);
        $waiting = $this->collection('2026-10-11 10:30:00');
        MarkerIoFailures::$directorySyncFails = true;
        try {
            $this->compensator()->compensate(2);
            $this->fail('Directory synchronization must precede collection changes.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unable to sync', $exception->getMessage());
        } finally {
            MarkerIoFailures::$directorySyncFails = false;
        }

        $this->assertSame('2026-10-11 10:30:00', $this->dateAdded($waiting));
        $this->assertSame($previous, $this->compensator()->lastActivity(), 'The outage must remain claimable after directory synchronization fails.');
        $this->assertSame(1, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
    }

    public function test_a_previous_test_activity_marker_is_not_reused(): void
    {
        $legacy = sys_get_temp_dir().'/nntmux-test-activity-'.getmypid();
        file_put_contents($legacy, '123');
        try {
            $this->assertNull($this->app->make(CollectionDowntimeCompensator::class)->lastActivity());
        } finally {
            unlink($legacy);
        }
    }

    public function test_failed_publication_and_rollback_keep_durable_recovery_evidence(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive($previous);
        $waiting = $this->collection('2026-10-11 10:30:00');
        MarkerIoFailures::$directorySyncCalls = 0;
        MarkerIoFailures::$directorySyncFailFromCall = 3;
        try {
            $this->compensator()->compensate(2);
            $this->fail('Failed rollback synchronization must be reported as unrecoverable.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unrecoverable', $exception->getMessage());
        } finally {
            MarkerIoFailures::$directorySyncFailFromCall = null;
        }

        $this->assertFileExists($this->marker.'.recovery');
        $this->assertSame('2026-10-11 10:30:00', $this->dateAdded($waiting));
        $this->assertSame(1, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
        $this->assertFileDoesNotExist($this->marker.'.recovery');
    }

    public function test_an_interrupted_publication_recovers_the_original_interval(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive(Carbon::now('UTC')->getTimestamp());
        file_put_contents($this->marker.'.recovery', json_encode(['previous' => base64_encode((string) $previous)], JSON_THROW_ON_ERROR)."\n");
        $waiting = $this->collection('2026-10-11 10:30:00');

        $this->assertSame(1, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
        $this->assertFileDoesNotExist($this->marker.'.recovery');
    }

    public function test_a_committed_recovery_record_never_reclaims_the_outage(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive(Carbon::now('UTC')->getTimestamp());
        file_put_contents($this->marker.'.recovery', json_encode(['previous' => base64_encode((string) $previous)], JSON_THROW_ON_ERROR)."\nCOMMITTED\n");
        $waiting = $this->collection('2026-10-11 11:30:00');

        $this->assertSame(0, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
        $this->assertFileDoesNotExist($this->marker.'.recovery');
    }

    public function test_a_failed_checkpoint_sync_restores_the_claimable_interval(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive($previous);
        $waiting = $this->collection('2026-10-11 10:30:00');
        MarkerIoFailures::$checkpointSyncFails = true;
        try {
            $this->compensator()->compensate(2);
            $this->fail('A failed checkpoint must abort publication.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unable to persist', $exception->getMessage());
        } finally {
            MarkerIoFailures::$checkpointSyncFails = false;
        }

        $this->assertSame($previous, $this->compensator()->lastActivity());
        $this->assertSame('2026-10-11 10:30:00', $this->dateAdded($waiting));
        $this->assertSame(1, $this->compensator()->compensate(2));
    }

    public function test_checkpoint_and_rollback_failure_cannot_discard_the_pending_outage(): void
    {
        $previous = Carbon::now('UTC')->subHour()->getTimestamp();
        $this->lastActive($previous);
        $waiting = $this->collection('2026-10-11 10:30:00');
        MarkerIoFailures::$directorySyncCalls = 0;
        MarkerIoFailures::$checkpointSyncFails = true;
        MarkerIoFailures::$directorySyncFailFromCall = 4;
        try {
            $this->compensator()->compensate(2);
            $this->fail('The failed rollback must preserve a pending outage.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Unrecoverable', $exception->getMessage());
        } finally {
            MarkerIoFailures::$checkpointSyncFails = false;
            MarkerIoFailures::$directorySyncFailFromCall = null;
        }

        // Model a crash losing the unsynced rollback rename, leaving the new marker.
        file_put_contents($this->marker, (string) Carbon::now('UTC')->getTimestamp());
        $this->assertSame(1, $this->compensator()->compensate(2));
        $this->assertSame('2026-10-11 11:30:00', $this->dateAdded($waiting));
    }

    public function test_tests_use_a_temporary_marker_not_the_real_one(): void
    {
        $this->app->make(CollectionDowntimeCompensator::class)->recordActivity();

        $this->assertFileDoesNotExist(storage_path('app/tmux/last-ingestion-activity.test-probe'));
        $this->assertNotSame(
            storage_path('app/tmux/last-ingestion-activity'),
            (new \ReflectionProperty(CollectionDowntimeCompensator::class, 'markerPath'))->getValue($this->app->make(CollectionDowntimeCompensator::class)),
        );
    }

    public function test_rows_from_before_a_fall_back_restart_that_reads_earlier_are_included(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/New_York');
        try {
            // Last activity 01:10 EST, restart 01:30 EST; added at 01:59 EDT, 11 minutes before.
            $lastActive = Carbon::parse('2026-11-01 06:10:00', 'UTC')->getTimestamp();
            Carbon::setTestNow(Carbon::createFromTimestamp($lastActive + 1200, 'UTC'));
            $this->lastActive($lastActive);
            $row = $this->collection('2026-11-01 01:59:00');

            $this->compensator()->compensate(2);

            $this->assertSame('2026-11-01 01:19:00', $this->dateAdded($row), '01:59 EDT plus 20 minutes is 01:19 EST.');
        } finally {
            date_default_timezone_set($previous);
        }
    }

    private function compensator(): CollectionDowntimeCompensator
    {
        return new CollectionDowntimeCompensator($this->marker);
    }

    private function lastActive(int $timestamp): void
    {
        @mkdir(\dirname($this->marker), 0775, true);
        file_put_contents($this->marker, (string) $timestamp);
    }

    private function collection(string $dateAdded): int
    {
        return DB::table('collections')->insertGetId(['dateadded' => $dateAdded]);
    }

    private function dateAdded(int $id): string
    {
        return (string) DB::table('collections')->where('id', $id)->value('dateadded');
    }
}
