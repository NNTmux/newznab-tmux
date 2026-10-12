<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\ScratchBudget;
use App\Services\Releases\Verification\ScratchInventory;
use App\Services\Releases\Verification\VerificationResetService;
use App\Services\Releases\Verification\VerificationWorkerLocks;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class ParallelVerificationWorkersTest extends ReleaseVerificationCandidatesTest
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['release_verification.candidate_workers' => 2, 'release_verification.free_bytes' => 0]);
    }

    public function test_parallel_slots_claim_distinct_candidates_and_exclude_an_active_older_version(): void
    {
        $events = array_values(array_filter(app(Schedule::class)->events(),
            static fn ($event): bool => str_contains($event->command ?? '', 'releases:verify-candidates')));
        $this->assertCount(2, $events);
        $this->assertStringContainsString('-d ffi.enable=true -d memory_limit=256M', $events[0]->command);
        $this->assertStringContainsString('--slot=1', $events[0]->command);
        $this->assertStringContainsString('--slot=2', $events[1]->command);
        $repository = app(CandidateRepository::class);
        foreach (['one', 'two'] as $name) {
            $repository->enqueue(['identity' => sha1($name), 'groups_id' => 1, 'files' => []]);
        }
        $first = new VerificationWorkerLocks;
        $second = new VerificationWorkerLocks;
        $third = new VerificationWorkerLocks;
        try {
            $this->assertTrue($first->start());
            $this->assertTrue($second->start());
            $this->assertFalse($third->start());
            $a = $repository->claim(fn (int $id): bool => $first->tryCandidate($id));
            $b = $repository->claim(fn (int $id): bool => $second->tryCandidate($id));
            $this->assertNotNull($a);
            $this->assertNotNull($b);
            $this->assertNotSame($a->id, $b->id);
            $this->assertNotSame($a->lease_token, $b->lease_token);
            DB::table('release_verification_candidates')->where('id', $a->id)->update([
                'version' => 2, 'status' => 'pending', 'lease_token' => null, 'lease_until' => null,
            ]);
            $second->releaseCandidate();
            $this->assertNull($repository->claim(fn (int $id): bool => $second->tryCandidate($id)));
            $first->releaseCandidate();
            $replacement = $repository->claim(fn (int $id): bool => $second->tryCandidate($id));
            $this->assertNotNull($replacement);
            $this->assertSame($a->id, $replacement->id);
            $this->assertSame(2, (int) $replacement->version);
        } finally {
            $first->close();
            $second->close();
            $third->close();
        }
    }

    public function test_candidate_workers_exclude_audit_and_reset_and_keep_lock_inodes(): void
    {
        $this->assertTrue(app(ScratchBudget::class)->initialize());
        $worker = new VerificationWorkerLocks;
        $this->assertTrue($worker->start());
        $root = (string) config('release_verification.storage_path');
        $inode = fileinode($root.'/worker.lock');
        $resetCalled = false;
        try {
            $this->assertSame(0, Artisan::call('releases:verify-repairable', ['--dry-run' => true]));
            $this->assertStringContainsString('retry the audit later', Artisan::output());
            try {
                app(VerificationResetService::class)->run(function () use (&$resetCalled): void {
                    $resetCalled = true;
                });
                $this->fail('An active candidate worker must exclude reset.');
            } catch (RuntimeException) {
                $this->assertFalse($resetCalled);
            }
        } finally {
            $worker->close();
        }
        app(VerificationResetService::class)->run(function () use (&$resetCalled): void {
            $resetCalled = true;
        });
        $this->assertTrue($resetCalled);
        $this->assertSame($inode, fileinode($root.'/worker.lock'));
        $exclusive = new VerificationWorkerLocks;
        try {
            $this->assertTrue($exclusive->tryExclusive());
            $this->assertFalse($worker->start());
        } finally {
            $exclusive->close();
        }
    }

    public function test_shared_scratch_reservations_refuse_oversubscription_and_survive_until_locked_reconciliation(): void
    {
        config(['release_verification.scratch_bytes' => 10]);
        $inventory = app(ScratchInventory::class);
        $inventory->register(1, 1);
        $inventory->register(2, 1);
        $root = (string) config('release_verification.storage_path');
        File::ensureDirectoryExists($root.'/scratch/1-1');
        file_put_contents($root.'/scratch/1-1/cache', 'abc');
        $budget = app(ScratchBudget::class);
        $budget->initialize();
        $one = (int) DB::table('release_verification_scratch')->where('candidate_id', 1)->value('id');
        $two = (int) DB::table('release_verification_scratch')->where('candidate_id', 2)->value('id');
        $this->assertSame(3, (int) DB::table('release_verification_scratch_budget')->value('reserved_bytes'));
        $budget->reserve($one, 7);
        try {
            $budget->reserve($two, 4);
            $this->fail('Two reservations must not each spend the full scratch quota.');
        } catch (RuntimeException) {
            $this->assertSame(7, (int) DB::table('release_verification_scratch_budget')->value('reserved_bytes'));
        }
        $this->travel(10)->minutes();
        $replacement = new VerificationWorkerLocks;
        try {
            $this->assertTrue($replacement->start());
            $this->assertTrue($replacement->tryCandidate(1));
            $freshBudget = app(ScratchBudget::class);
            $freshBudget->initialize();
            $this->assertSame(7, (int) DB::table('release_verification_scratch_budget')->value('reserved_bytes'));
            $freshBudget->reconcile($one, $replacement);
            $this->assertSame(3, (int) DB::table('release_verification_scratch_budget')->value('reserved_bytes'));
            $freshBudget->reserve($two, 7);
            $this->assertSame(10, (int) DB::table('release_verification_scratch_budget')->value('reserved_bytes'));
        } finally {
            $replacement->close();
        }
    }
}
