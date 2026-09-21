<?php

declare(strict_types=1);

namespace App\Services\Releases;

use App\Facades\Search;
use App\Models\Release;
use App\Services\Nzb\NzbService;
use App\Services\ReleaseImageService;
use App\Support\ReleaseSearchIndexSync;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service for managing releases (delete, update, export).
 */
class ReleaseManagementService
{
    public function __construct() {}

    /**
     * @param  array<string, mixed>  $list
     *
     * @throws \Exception
     */
    public function deleteMultiple(int|array|string $list): void
    {
        $list = (array) $list;

        $nzb = app(NzbService::class);
        $releaseImage = new ReleaseImageService;

        foreach ($list as $identifier) {
            $this->deleteSingleWithService(['g' => $identifier, 'i' => false], $nzb, $releaseImage);
        }
    }

    /**
     * Deletes a single release by GUID, and all the corresponding files.
     *
     * @param  array<string, mixed>  $identifiers  ['g' => Release GUID(mandatory), 'id => ReleaseID(optional, pass
     *                                             false)]
     *
     * @throws \Exception
     */
    public function deleteSingle(array $identifiers, NzbService $nzb, ReleaseImageService $releaseImage): void
    {
        // Delete NZB from disk.
        $nzbPath = $nzb->nzbPath($identifiers['g']);
        if (! empty($nzbPath)) {
            File::delete($nzbPath);
        }

        // Delete images.
        $releaseImage->delete($identifiers['g']);

        // Get release ID if not provided
        if ($identifiers['i'] === false) {
            $release = Release::query()->where('guid', $identifiers['g'])->first(['id']);
            if ($release !== null) {
                $identifiers['i'] = $release->id;
            }
        }

        // Delete from search index
        if (! empty($identifiers['i'])) {
            Search::deleteRelease((int) $identifiers['i']);
        }

        // Delete from DB.
        Release::whereGuid($identifiers['g'])->delete();
    }

    /**
     * Alias for deleteSingle for backwards compatibility.
     *
     * @param  array<string, mixed>  $identifiers  ['g' => Release GUID(mandatory), 'i => ReleaseID(optional, pass false)]
     *
     * @throws \Exception
     */
    public function deleteSingleWithService(array $identifiers, NzbService $nzb, ReleaseImageService $releaseImage): void
    {
        $this->deleteSingle($identifiers, $nzb, $releaseImage);
    }

    /**
     * Delete a bounded set of releases while batching search and database work.
     *
     * @param  iterable<int, object|array<string, mixed>>  $releases
     */
    public function deleteBatch(iterable $releases, NzbService $nzb, ReleaseImageService $releaseImage): int
    {
        $rows = collect($releases)
            ->map(static fn (object|array $release): array => [
                'id' => (int) data_get($release, 'id'),
                'guid' => (string) data_get($release, 'guid'),
            ])
            ->filter(static fn (array $release): bool => $release['id'] > 0 && $release['guid'] !== '')
            ->unique('id')
            ->values();

        if ($rows->isEmpty()) {
            return 0;
        }

        foreach ($rows as $release) {
            try {
                $nzb->deleteNzb($release['guid']);
                $releaseImage->delete($release['guid']);
            } catch (Throwable $e) {
                Log::error('Release batch filesystem cleanup failed', [
                    'release_id' => $release['id'],
                    'guid' => $release['guid'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $ids = $rows->pluck('id')->all();

        try {
            Search::deleteReleases($ids);
        } catch (Throwable $e) {
            Log::error('Release batch search cleanup failed', [
                'release_ids' => $ids,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            return Release::query()->whereIn('id', $ids)->delete();
        } catch (Throwable $e) {
            Log::error('Release batch database cleanup failed', [
                'release_ids' => $ids,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * @return bool|int
     */
    public function updateMulti(mixed $guids, mixed $category, mixed $grabs, mixed $videoId, mixed $episodeId, mixed $anidbId, mixed $imdbId)
    {
        if (! \is_array($guids) || \count($guids) < 1) {
            return false;
        }

        $update = [
            'categories_id' => $category === -1 ? 'categories_id' : $category,
            'grabs' => $grabs,
            'videos_id' => $videoId,
            'tv_episodes_id' => $episodeId,
            'anidbid' => $anidbId,
            'imdbid' => $imdbId,
        ];

        $releaseIds = Release::query()->whereIn('guid', $guids)->pluck('id');
        $updated = Release::query()->whereIn('guid', $guids)->update($update);
        ReleaseSearchIndexSync::forIds($releaseIds);

        return $updated;
    }

    /**
     * @param  list<string>  $guids
     */
    public function bulkUpdateCategory(array $guids, int $categoryId): int
    {
        $guids = array_values(array_filter($guids));
        if ($guids === [] || $categoryId <= 0) {
            return 0;
        }

        $updated = 0;

        DB::transaction(function () use ($guids, $categoryId, &$updated): void {
            $releaseIds = Release::query()
                ->whereIn('guid', $guids)
                ->pluck('id');

            $updated = Release::query()
                ->whereIn('guid', $guids)
                ->update(['categories_id' => $categoryId, 'iscategorized' => 1]);

            if ($updated > 0) {
                $this->syncReleasesToSearchIndex($releaseIds);
                Release::clearAdminReleasesRangeCache();
            }
        });

        return $updated;
    }

    /**
     * Re-index releases after query-builder updates that bypass {@see ReleaseObserver}.
     *
     * @param  Collection<int, int|string>|iterable<int|string>  $releaseIds
     */
    private function syncReleasesToSearchIndex(iterable $releaseIds): void
    {
        foreach ($releaseIds as $releaseId) {
            $intId = (int) $releaseId;
            if ($intId <= 0) {
                continue;
            }

            try {
                Search::updateRelease($intId);
            } catch (Throwable $e) {
                Log::error('ReleaseManagementService: Failed to sync release to search index after category change', [
                    'release_id' => $intId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return Release[]|Builder[]|\Illuminate\Database\Eloquent\Collection<int, mixed>|\Illuminate\Database\Query\Builder[]|Collection<int, mixed>
     */
    public function getForExport(string $postFrom = '', string $postTo = '', string $groupID = '') // @phpstan-ignore missingType.generics
    {
        $query = Release::query()
            ->select(['r.searchname', 'r.guid', 'g.name as gname', DB::raw("CONCAT(cp.title,'_',c.title) AS catName")])
            ->from('releases as r')
            ->leftJoin('categories as c', 'c.id', '=', 'r.categories_id')
            ->leftJoin('root_categories as cp', 'cp.id', '=', 'c.root_categories_id')
            ->leftJoin('usenet_groups as g', 'g.id', '=', 'r.groups_id');

        if ($groupID !== '') {
            $query->where('r.groups_id', $groupID);
        }

        if ($postFrom !== '') {
            $dateParts = explode('/', $postFrom);
            if (\count($dateParts) === 3) {
                $query->where('r.postdate', '>', $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0].'00:00:00');
            }
        }

        if ($postTo !== '') {
            $dateParts = explode('/', $postTo);
            if (\count($dateParts) === 3) {
                $query->where('r.postdate', '<', $dateParts[2].'-'.$dateParts[1].'-'.$dateParts[0].'23:59:59');
            }
        }

        return $query->get();
    }

    /**
     * @return mixed|string
     */
    public function getEarliestUsenetPostDate(): mixed
    {
        $row = Release::query()->selectRaw("DATE_FORMAT(min(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    /**
     * @return mixed|string
     */
    public function getLatestUsenetPostDate(): mixed
    {
        $row = Release::query()->selectRaw("DATE_FORMAT(max(postdate), '%d/%m/%Y') AS postdate")->first();

        return $row === null ? '01/01/2014' : $row['postdate'];
    }

    /**
     * @return array<string, mixed>
     */
    public function getReleasedGroupsForSelect(bool $blnIncludeAll = true): array
    {
        $groups = Release::query()
            ->selectRaw('DISTINCT g.id, g.name')
            ->leftJoin('usenet_groups as g', 'g.id', '=', 'releases.groups_id')
            ->get();
        $temp_array = [];

        if ($blnIncludeAll) {
            $temp_array[-1] = '--All Groups--';
        }

        foreach ($groups as $group) {
            $temp_array[$group['id']] = $group['name'];
        }

        return $temp_array;
    }
}
