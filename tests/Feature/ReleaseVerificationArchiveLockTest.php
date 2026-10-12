<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Releases\Verification\CandidateRepository;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class ReleaseVerificationArchiveLockTest extends ReleaseVerificationCandidatesTest
{
    public function test_missing_collection_does_not_request_shared_capacity(): void
    {
        $capacityReads = 0;
        DB::listen(static function (QueryExecuted $query) use (&$capacityReads): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'release_verification_state')) {
                $capacityReads++;
            }
        });

        app(CandidateRepository::class)->archive(123);

        $this->assertSame(0, $capacityReads);
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
    }

    public function test_changed_hash_between_lookup_and_lock_retains_headers(): void
    {
        $id = DB::table('collections')->insertGetId(['collectionhash' => sha1('original', true),
            'subject' => 'Movie', 'fromname' => 'poster', 'totalfiles' => 1, 'groups_id' => 1,
            'date' => now()->format('Y-m-d H:i:s')]);
        $changedHash = sha1('replacement', true);
        $changed = false;
        DB::listen(static function (QueryExecuted $query) use ($id, $changedHash, &$changed): void {
            if (! $changed && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'collectionhash')
                && str_contains($query->sql, 'collections') && $query->bindings === [$id]) {
                $changed = true;
                DB::table('collections')->where('id', $id)->update(['collectionhash' => $changedHash]);
            }
        });

        app(CandidateRepository::class)->archive($id);

        $this->assertTrue($changed);
        $this->assertSame($changedHash, DB::table('collections')->where('id', $id)->value('collectionhash'));
        $this->assertSame(0, DB::table('release_verification_candidates')->count());
        $this->assertSame(0, (int) DB::table('release_verification_state')->value('pending_bytes'));
    }
}
