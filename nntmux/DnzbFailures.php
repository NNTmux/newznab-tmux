<?php

namespace nntmux;

use App\Models\Release;
use App\Models\DnzbFailure;
use Illuminate\Support\Facades\DB;

/**
 * Class DnzbFailures.
 */
class DnzbFailures
{
    /**
     * @var array Class instances.
     * @throws \Exception
     */
    public function __construct(array $options = [])
    {
        //
    }

    /**
     * Read failed downloads count for requested release_id.
     *
     *
     * @param $relId
     *
     * @return bool|mixed
     */
    public function getFailedCount($relId)
    {
        $result = DnzbFailure::query()->where('release_id', $relId)->value('failed');
        if (! empty($result)) {
            return $result;
        }

        return false;
    }

    /**
     * @return int
     */
    public function getCount(): int
    {
        return DnzbFailure::query()->count('release_id');
    }

    /**
     * Get a range of releases. used in admin manage list.
     *
     *
     * @param $start
     * @param $num
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public function getFailedRange($start, $num)
    {
        $failedList = Release::query()
            ->select(['name', 'searchname', 'size', 'guid', 'totalpart', 'postdate', 'adddate', 'grabs', DB::raw("CONCAT(cp.title, ' > ', c.title) AS category_name")])
            ->rightJoin('dnzb_failures', 'dnzb_failures.release_id', '=', 'releases.id')
            ->leftJoin('categories as c', 'c.id', '=', 'releases.categories_id')
            ->leftJoin('categories as cp', 'cp.id', '=', 'c.parentid')
            ->orderBy('postdate', 'desc');
        if ($start !== false) {
            $failedList->limit($num)->offset($start);
        }

        return $failedList->get();
    }

    /**
     * Retrieve alternate release with same or similar searchname.
     *
     * @param string $guid
     * @param string $userid
     *
     * @return string|array
     * @throws \Exception
     */
    public function getAlternate($guid, $userid)
    {
        $rel = Release::query()->where('guid', $guid)->first(['id', 'searchname', 'categories_id']);

        if ($rel === null) {
            return false;
        }
        DnzbFailure::insertIgnore(['release_id' => $rel['id'], 'users_id' => $userid, 'failed' => 1]);

        $alternate = Release::query()
            ->leftJoin('dnzb_failures as df', 'df.release_id', '=', 'releases.id')
            ->where('searchname', 'LIKE', $rel['searchname'])
            ->where('df.release_id', '=', null)
            ->where('categories_id', $rel['categories_id'])
            ->where('id', $rel['id'])
            ->orderBy('postdate', 'desc')
            ->first(['guid']);

        return $alternate;
    }
}
