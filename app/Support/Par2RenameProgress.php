<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class Par2RenameProgress
{
    /** @return array{signature: string, offset: int, retry_indexes: list<int>}|null */
    public static function get(int $releaseId): ?array
    {
        $row = DB::table('par2_rename_progress')->where('releases_id', $releaseId)->first();
        if ($row === null) {
            return null;
        }
        $retry = json_decode((string) $row->retry_indexes, true, flags: JSON_THROW_ON_ERROR);

        return ['signature' => (string) $row->signature, 'offset' => (int) $row->offset,
            'retry_indexes' => is_array($retry) ? array_values(array_filter($retry, is_int(...))) : []];
    }

    /** @param list<int> $retryIndexes */
    public static function save(int $releaseId, string $signature, int $offset, array $retryIndexes): bool
    {
        return DB::transaction(function () use ($releaseId, $signature, $offset, $retryIndexes): bool {
            $release = DB::table('releases')->where('id', $releaseId)->lockForUpdate()
                ->first(['isrenamed', 'proc_par2', 'predb_id']);
            if ($release === null || (int) $release->isrenamed === 1 || (int) $release->proc_par2 === 1 || (int) $release->predb_id > 0) {
                DB::table('par2_rename_progress')->where('releases_id', $releaseId)->delete();

                return false;
            }
            DB::table('par2_rename_progress')->updateOrInsert(['releases_id' => $releaseId],
                ['signature' => $signature, 'offset' => $offset, 'retry_indexes' => json_encode($retryIndexes, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return true;
        });
    }

    public static function forget(int $releaseId): void
    {
        try {
            DB::table('par2_rename_progress')->where('releases_id', $releaseId)->delete();
        } catch (Throwable $e) {
            Log::warning('Could not clear completed PAR2 probe progress', ['release_id' => $releaseId, 'error' => $e->getMessage()]);
        }
    }
}
