<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseComment.
 *
 * @property int $id
 * @property int $releases_id FK to releases.id
 * @property string $text
 * @property bool $isvisible
 * @property bool $issynced
 * @property string|null $gid
 * @property string|null $cid
 * @property string $text_hash
 * @property string $username
 * @property int $users_id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property string|null $host
 * @property bool $shared
 * @property string $shareid
 * @property string $siteid
 * @property int|null $sourceid
 * @property mixed $nzb_guid
 * @property-read \App\Models\Release $release
 * @property-read \App\Models\User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereCid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereGid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereHost($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereIssynced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereIsvisible($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereNzbGuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereShared($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereShareid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereSiteid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereSourceid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereText($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereTextHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment whereUsersId($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseComment query()
 */
class ReleaseComment extends Model
{
    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function release()
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * Get a comment by id.
     *
     *
     * @param $id
     * @return \Illuminate\Database\Eloquent\Model|null|static
     */
    public static function getCommentById($id)
    {
        return self::query()->where('id', $id)->first();
    }

    /**
     * @param $id
     * @return array
     */
    public static function getComments($id)
    {
        return self::query()->where('releases_id', $id)->orderBy('created_at', 'desc')->get()->toArray();
    }

    /**
     * @return int
     */
    public static function getCommentCount(): int
    {
        return self::query()->count(['id']);
    }

    /**
     * Delete single comment on the site.
     *
     * @param $id
     */
    public static function deleteComment($id): void
    {
        $res = self::getCommentById($id);
        if ($res) {
            self::query()->where('id', $id)->delete();
            self::updateReleaseCommentCount($res['gid']);
        }
    }

    /**
     * Add a release_comments row.
     *
     *
     * @param $id
     * @param $gid
     * @param $text
     * @param $userid
     * @param $host
     * @return int
     *
     * @throws \Exception
     */
    public static function addComment($id, $gid, $text, $userid, $host): int
    {
        if ((int) Settings::settingValue('..storeuserips') !== 1) {
            $host = '';
        }

        $username = User::query()->where('id', $userid)->first(['username']);
        $username = ($username === null ? 'ANON' : $username['username']);

        $comid = self::query()
            ->insertGetId(
                [
                    'releases_id' => $id,
                    'gid' => $gid,
                    'text' => $text,
                    'users_id' => $userid,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'host' => $host,
                    'username' => $username,
                ]
            );
        self::updateReleaseCommentCount($id);

        return $comid;
    }

    /**
     * Get release_comments rows by limit.
     *
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getCommentsRange()
    {
        $range = self::query()
            ->select(['release_comments.*', 'releases.guid'])
            ->leftJoin('releases', 'releases.id', '=', 'release_comments.releases_id')
            ->orderBy('release_comments.created_at', 'desc');

        return $range->paginate(config('nntmux.items_per_page'));
    }

    /**
     * Update the denormalised count of comments for a release.
     *
     * @param $gid
     */
    public static function updateReleaseCommentCount($gid): void
    {
        $commentCount = self::query()->where('gid', '=', 'releases.gid')->where('isvisible', '=', 1)->count(['id']);
        Release::query()->where('gid', $gid)->update(['comments' => $commentCount]);
    }

    /**
     * Get a count of all comments for a user.
     *
     * @param $uid
     * @return int
     */
    public static function getCommentCountForUser($uid): int
    {
        $res = self::query()->where(['users_id' => $uid, 'isvisible' => 1])->count(['id']);

        return $res;
    }

    /**
     * @param $uid
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public static function getCommentsForUserRange($uid)
    {
        return self::query()
            ->select(['release_comments.*', 'r.guid', 'r.searchname', 'u.username'])
            ->join('releases as r', 'r.id', '=', 'release_comments.releases_id')
            ->leftJoin('users as u', 'u.id', '=', 'release_comments.users_id')
            ->where('users_id', $uid)
            ->orderBy('created_at', 'desc')
            ->paginate(config('nntmux.items_per_page'));
    }
}
