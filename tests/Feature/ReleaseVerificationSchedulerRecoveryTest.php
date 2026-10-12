<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\NNTP\NNTPService;
use App\Services\Releases\Verification\CandidateRepository;
use App\Services\YencService;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ReleaseVerificationSchedulerRecoveryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/scheduler-recovery-'.bin2hex(random_bytes(8)));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'release_verification.storage_path' => $this->directory, 'release_verification.scheduled' => true,
            'release_verification.candidate_workers' => 1, 'release_verification.free_bytes' => 0, 'release_verification.bytes_per_second' => 0,
            'cache.default' => 'array']);
        DB::purge();
        foreach (['2026_10_11_154028_create_release_verification_candidates_table.php',
            '2026_10_11_175742_create_release_verification_audit_retries_table.php',
            '2026_10_12_004819_create_release_verification_scratch_table.php',
            '2026_10_12_053000_add_shared_verification_scratch_budget.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        Schema::create('usenet_groups', static function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        DB::table('usenet_groups')->insert(['id' => 1, 'name' => 'alt.test']);
        app(CandidateRepository::class)->enqueue(['identity' => sha1('scheduler-recovery'), 'groups_id' => 1,
            'expected_files' => 2, 'files' => [['key' => 'movie', 'subject' => 'movie.mkv', 'totalparts' => 1,
                'segments' => [['number' => 1, 'messageid' => 'scheduled@article', 'bytes' => 5]]]]]);
    }

    protected function tearDown(): void
    {
        try {
            File::deleteDirectory($this->directory);
        } finally {
            parent::tearDown();
        }
    }

    public function test_retained_scheduler_mutex_cannot_block_next_minute_or_expired_database_lease_recovery(): void
    {
        $event = $this->verificationEvent();
        // Simulate an abrupt worker interruption by retaining its cache mutex and database lease.
        $this->assertTrue($event->mutex->create($event));
        DB::table('release_verification_candidates')->update(['status' => 'verifying', 'lease_token' => 'interrupted-worker',
            'lease_until' => now()->addMinutes(5)]);
        $dispatches = 0;
        $event->before(function () use (&$dispatches): void {
            $dispatches++;
            $this->assertSame(0, Artisan::call('releases:verify-candidates', ['--limit' => 1]));
            throw new SchedulerDispatchIntercept;
        });
        $this->mock(NNTPService::class, static function ($mock): void {
            $mock->shouldReceive('doConnect')->once()->with(false)->andReturn(true);
            $mock->shouldReceive('verificationArticle')->once()->andReturn((new YencService)->encode('hello', 'movie.mkv'));
            $mock->shouldReceive('doQuit')->andReturn(null);
        });
        $this->travel(1)->minutes();
        $this->assertTrue($this->dispatchMinute($event));
        $this->assertSame(1, $dispatches);
        $this->assertSame('interrupted-worker', DB::table('release_verification_candidates')->value('lease_token'));
        $this->assertTrue($event->mutex->exists($event));

        $this->travel(5)->minutes();
        $this->assertTrue($this->dispatchMinute($event));
        $this->assertSame(2, $dispatches);
        $candidate = DB::table('release_verification_candidates')->sole();
        $this->assertSame('unknown', $candidate->status);
        $this->assertNull($candidate->lease_token);
        $this->assertSame('Declared file inventory cannot be proved complete.', $candidate->reason);
        $this->assertTrue($event->mutex->exists($event), 'The retained legacy scheduler mutex is ignored without needing a cache flush.');
    }

    public function test_active_verifier_worker_rejects_a_second_scheduled_dispatch_before_another_fetch(): void
    {
        $event = $this->verificationEvent();
        $dispatches = 0;
        $event->before(function () use (&$dispatches): void {
            $dispatches++;
            $this->assertSame(0, Artisan::call('releases:verify-candidates', ['--limit' => 1]));
            throw new SchedulerDispatchIntercept;
        });
        $this->mock(NNTPService::class, function ($mock) use ($event): void {
            $mock->shouldReceive('doConnect')->once()->with(false)->andReturn(true);
            $mock->shouldReceive('verificationArticle')->once()->andReturnUsing(function () use ($event): string {
                $token = DB::table('release_verification_candidates')->value('lease_token');
                $this->assertSame('verifying', DB::table('release_verification_candidates')->value('status'));
                $this->assertTrue($this->dispatchMinute($event));
                $this->assertStringContainsString('Verification worker is already running.', Artisan::output());
                $this->assertSame($token, DB::table('release_verification_candidates')->value('lease_token'));

                return (new YencService)->encode('hello', 'movie.mkv');
            });
            $mock->shouldReceive('doQuit')->andReturn(null);
        });

        $this->assertTrue($this->dispatchMinute($event));

        $this->assertSame(2, $dispatches);
        $this->assertSame('unknown', DB::table('release_verification_candidates')->value('status'));
        $this->assertSame(1, DB::table('release_verification_scratch')->count());
    }

    public function test_operator_can_disable_scheduled_verification_without_claiming_work(): void
    {
        $event = $this->verificationEvent();
        config(['release_verification.scheduled' => false]);

        $this->assertFalse($this->dispatchMinute($event));
        $this->assertSame('pending', DB::table('release_verification_candidates')->value('status'));
        $this->assertNull(DB::table('release_verification_candidates')->value('lease_token'));
    }

    private function verificationEvent(): Event
    {
        foreach (app(Schedule::class)->events() as $event) {
            if (is_string($event->command) && str_contains($event->command, 'releases:verify-candidates --limit=1')) {
                return $event;
            }
        }
        $this->fail('The verification schedule must be registered.');
    }

    /** Intercept background dispatch after Laravel due, filter and overlap checks; run only the test worker. */
    private function dispatchMinute(Event $event): bool
    {
        if (! $event->isDue($this->app) || ! $event->filtersPass($this->app)) {
            return false;
        }
        try {
            $event->run($this->app);
        } catch (SchedulerDispatchIntercept) {
            return true;
        }

        return false;
    }
}

final class SchedulerDispatchIntercept extends RuntimeException {}
