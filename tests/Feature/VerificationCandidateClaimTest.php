<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Releases\Verification\CandidateRepository;
use App\Services\Releases\Verification\VerificationWorkerLocks;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use PDOException;

class VerificationCandidateClaimTest extends ReleaseVerificationCandidatesTest
{
    public function test_a_retry_that_loses_eligibility_does_not_advance_with_a_held_file_mutex(): void
    {
        $repository = app(CandidateRepository::class);
        foreach (['first', 'second'] as $name) {
            $repository->enqueue(['identity' => sha1($name), 'groups_id' => 1, 'files' => []]);
        }
        $first = (int) DB::table('release_verification_candidates')->orderBy('id')->value('id');
        $once = false;
        DB::listen(static function (QueryExecuted $query) use ($first, &$once): void {
            if (! $once && str_starts_with($query->sql, 'update "release_verification_candidates"')
                && in_array('verifying', $query->bindings, true)) {
                $once = true;
                DB::afterRollBack(static function () use ($first): void {
                    DB::table('release_verification_candidates')->where('id', $first)
                        ->update(['lease_until' => now()->addHour()]);
                });
                throw new PDOException('Deadlock found when trying to get lock; try restarting transaction', 40001);
            }
        });
        $locks = new VerificationWorkerLocks;
        $attempts = [];
        try {
            $this->assertTrue($locks->start());
            $claim = $repository->claim(static function (int $id) use ($locks, &$attempts): bool {
                $attempts[] = $id;

                return $locks->tryCandidate($id);
            });
            $this->assertNull($claim);
            $this->assertSame([$first], $attempts);
            $this->assertTrue($once);
        } finally {
            $locks->close();
        }
    }
}
