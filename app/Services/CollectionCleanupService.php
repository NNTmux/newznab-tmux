<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CollectionCleanupService
{
    public function __construct()
    {}

    /**
     * Deletes finished/old collections, cleans orphans, and removes collections missed after NZB creation.
     * Mirrors the previous ProcessReleases::deleteCollections logic.
     *
     * @return int total deleted rows across operations (approximate)
     */
    public function deleteFinishedAndOrphans(bool $echoCLI): int
    {
        $startTime = now()->toImmutable();
        $deletedCount = 0;

        if ($echoCLI) {
            echo cli()->header('Process Releases -> Delete finished collections.'.PHP_EOL).
                cli()->primary(sprintf(
                    'Deleting collections/binaries/parts older than %d hours.',
                    Settings::settingValue('partretentionhours')
                ), true);
        }

        // Batch-delete old collections using a safe id-subselect to avoid read-then-delete races.
        $cutoff = now()->subHours(Settings::settingValue('partretentionhours'));
        $batchDeleted = 0;
        $maxRetries = 5;
        do {
            $affected = 0;
            $attempt = 0;
            do {
                try {
                    // Delete by id list derived in a nested subquery to avoid "Record has changed since last read".
                    $affected = DB::affectingStatement(
                        'DELETE FROM collections WHERE id IN (
                            SELECT id FROM (
                                SELECT id FROM collections WHERE dateadded < ? ORDER BY id LIMIT 500
                            ) AS x
                        )',
                        [$cutoff]
                    );
                    break; // success
                } catch (\Throwable $e) {
                    // Retry on lock/timeout errors
                    $attempt++;
                    if ($attempt >= $maxRetries) {
                        if ($echoCLI) {
                            cli()->error('Cleanup delete failed after retries: '.$e->getMessage());
                        }
                        break;
                    }
                    usleep(20000 * $attempt);
                }
            } while (true);

            $batchDeleted += $affected;
            if ($affected < 500) {
                break;
            }
            // Brief pause to reduce pressure on the lock manager in busy systems.
            usleep(10000);
        } while (true);

        $deletedCount += $batchDeleted;

        if ($echoCLI) {
            $elapsed = now()->diffInSeconds($startTime, true);
            cli()->primary(
                'Finished deleting '.$batchDeleted.' old collections/binaries/parts in '.
                $elapsed.Str::plural(' second', $elapsed),
                true
            );
        }

        // Occasionally prune CBP orphans (low frequency to avoid heavy load).
        if (random_int(0, 200) <= 1) {
            if ($echoCLI) {
                echo cli()->header('Process Releases -> Remove CBP orphans.'.PHP_EOL).
                    cli()->primary('Deleting orphaned collections.');
            }

            $deleted = 0;
            // NOTE: This JOIN DELETE can be heavy; consider batching if it becomes an issue in practice.
            $deleteQuery = Collection::query()
                ->whereNull('binaries.id')
                ->orWhereNull('parts.binaries_id')
                ->leftJoin('binaries', 'collections.id', '=', 'binaries.collections_id')
                ->leftJoin('parts', 'binaries.id', '=', 'parts.binaries_id')
                ->delete();

            if ($deleteQuery > 0) {
                $deleted = $deleteQuery;
                $deletedCount += $deleted;
            }

            $totalTime = now()->diffInSeconds($startTime);

            if ($echoCLI) {
                cli()->primary('Finished deleting '.$deleted.' orphaned collections in '.$totalTime.Str::plural(' second', $totalTime), true);
            }
        }

        if ($echoCLI) {
            cli()->primary('Deleting collections that were missed after NZB creation.', true);
        }

        $deleted = 0;
        $collections = Collection::query()
            ->where('releases.nzbstatus', '=', 1)
            ->leftJoin('releases', 'releases.id', '=', 'collections.releases_id')
            ->select('collections.id')
            ->get();

        foreach ($collections as $collection) {
            $deleted++;
            Collection::query()->where('id', $collection->id)->delete();
        }
        $deletedCount += $deleted;

        $totalTime = now()->diffInSeconds($startTime, true);

        if ($echoCLI) {
            cli()->primary(
                'Finished deleting '.$deleted.' collections missed after NZB creation in '.($totalTime).Str::plural(' second', $totalTime).
                PHP_EOL.'Removed '.number_format($deletedCount).' parts/binaries/collection rows in '.$totalTime.Str::plural(' second', $totalTime),
                true
            );
        }

        return $deletedCount;
    }
}
