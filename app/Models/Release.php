<?php

namespace App\Models;

use nntmux\SphinxSearch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

class Release extends Model
{
    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function group()
    {
        return $this->belongsTo(Group::class, 'groups_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function download()
    {
        return $this->hasMany(UserDownload::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userRelease()
    {
        return $this->hasMany(UsersRelease::class, 'releases_id');
    }

    public function file()
    {
        return $this->hasMany(ReleaseFile::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(Category::class, 'categories_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function predb()
    {
        return $this->belongsTo(Predb::class, 'predb_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function failed()
    {
        return $this->hasMany(DnzbFailure::class, 'release_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function releaseExtra()
    {
        return $this->hasMany(ReleaseExtraFull::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function nfo()
    {
        return $this->hasOne(ReleaseNfo::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comment()
    {
        return $this->hasMany(ReleaseComment::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function releaseGroup()
    {
        return $this->hasMany(ReleasesGroups::class, 'releases_id');
    }

    /**
     * Insert a single release returning the ID on success or false on failure.
     *
     * @param array $parameters Insert parameters, must be escaped if string.
     *
     * @return bool|int
     * @throws \Exception
     */
    public static function insertRelease(array $parameters = [])
    {
        $passwordStatus = ((int) Settings::settingValue('..checkpasswordedrar') === 1 ? -1 : 0);
        $parameters['id'] = self::query()
            ->insertGetId(
                [
                    'name' => $parameters['name'],
                    'searchname' => $parameters['searchname'],
                    'totalpart' => $parameters['totalpart'],
                    'groups_id' => $parameters['groups_id'],
                    'adddate' => Carbon::now(),
                    'guid' => $parameters['guid'],
                    'leftguid' => $parameters['guid'][0],
                    'postdate' => $parameters['postdate'],
                    'fromname' => $parameters['fromname'],
                    'size' => $parameters['size'],
                    'passwordstatus' => $passwordStatus,
                    'haspreview' => -1,
                    'categories_id' => $parameters['categories_id'],
                    'nfostatus' => -1,
                    'nzbstatus' => $parameters['nzbstatus'],
                    'isrenamed' => $parameters['isrenamed'],
                    'iscategorized' => 1,
                    'predb_id' => $parameters['predb_id'],
                ]
            );

        (new SphinxSearch())->insertRelease($parameters);

        return $parameters['id'];
    }

    /**
     * Used for release edit page on site.
     *
     * @param int $ID
     * @param string $name
     * @param string $searchName
     * @param string $fromName
     * @param int $categoryID
     * @param int $parts
     * @param int $grabs
     * @param int $size
     * @param string $postedDate
     * @param string $addedDate
     * @param        $videoId
     * @param        $episodeId
     * @param int $imDbID
     * @param int $aniDbID
     * @throws \Exception
     */
    public static function updateRelease($ID, $name, $searchName, $fromName, $categoryID, $parts, $grabs, $size, $postedDate, $addedDate, $videoId, $episodeId, $imDbID, $aniDbID): void
    {
        self::query()->where('id', $ID)->update(
            [
                'name' => $name,
                'searchname' => $searchName,
                'fromname' => $fromName,
                'categories_id' => $categoryID,
                'totalpart' => $parts,
                'grabs' => $grabs,
                'size' => $size,
                'postdate' => $postedDate,
                'adddate' => $addedDate,
                'videos_id' => $videoId,
                'tv_episodes_id' => $episodeId,
                'imdbid' => $imDbID,
                'anidbid' => $aniDbID,
            ]
        );
        (new SphinxSearch())->updateRelease($ID);
    }

    /**
     * @param string $guid
     * @throws \Exception
     */
    public static function updateGrab($guid): void
    {
        $updateGrabs = ((int) Settings::settingValue('..grabstatus') !== 0);
        if ($updateGrabs) {
            self::query()->where('guid', $guid)->increment('grabs');
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getCatByRelId($id)
    {
        return self::query()->where('id', $id)->first(['categories_id']);
    }

    /**
     * @param $videoId
     * @return int
     */
    public static function removeVideoIdFromReleases($videoId): int
    {
        return self::query()->where('videos_id', $videoId)->update(['videos_id' => 0, 'tv_episodes_id' => 0]);
    }

    /**
     * @param $anidbID
     * @return int
     */
    public static function removeAnidbIdFromReleases($anidbID): int
    {
        return self::query()->where('anidbid', $anidbID)->update(['anidbid' => -1]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopDownloads()
    {
        $releases = Cache::get('topdownloads');
        if ($releases !== null) {
            return $releases;
        }

        $releases = self::query()
            ->where('grabs', '>', 0)
            ->select(['id', 'searchname', 'guid', 'adddate'])
            ->selectRaw('SUM(grabs) as grabs')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(grabs) > 0')
            ->orderBy('grabs', 'desc')
            ->limit(10)
            ->get();

        $expiresAt = Carbon::now()->addSeconds(NN_CACHE_EXPIRY_LONG);
        Cache::put('topdownloads', $releases, $expiresAt);

        return $releases;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|static[]
     */
    public static function getTopComments()
    {
        $comments = Cache::get('topcomments');
        if ($comments !== null) {
            return $comments;
        }

        $comments = self::query()
            ->where('comments', '>', 0)
            ->select(['id', 'guid', 'searchname'])
            ->selectRaw('SUM(comments) AS comments')
            ->groupBy('id', 'searchname', 'adddate')
            ->havingRaw('SUM(comments) > 0')
            ->orderBy('comments', 'desc')
            ->limit(10)
            ->get();
        $expiresAt = Carbon::now()->addSeconds(NN_CACHE_EXPIRY_LONG);
        Cache::put('topcomments', $comments, $expiresAt);

        return $comments;
    }
}
