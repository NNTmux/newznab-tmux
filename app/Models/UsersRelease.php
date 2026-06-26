<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\UsersRelease.
 *
 * @property int $id
 * @property int $users_id
 * @property int $releases_id FK to releases.id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Release $release
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease whereUsersId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\UsersRelease query()
 */
class UsersRelease extends Model
{
    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }

    public static function delCartForUser(mixed $uid): void
    {
        self::query()->where('users_id', $uid)->delete();
    }

    /**
     * @return int|Builder
     */
    public static function addCart(mixed $uid, mixed $releaseid) // @phpstan-ignore missingType.generics
    {
        return self::query()->insertGetId(
            [
                'users_id' => $uid,
                'releases_id' => $releaseid,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Add multiple releases to a user's cart in one insert statement.
     *
     * @param  list<int>  $releaseIds
     */
    public static function addCartForReleases(int $uid, array $releaseIds): int
    {
        if ($releaseIds === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(
            static fn (int $releaseId): array => [
                'users_id' => $uid,
                'releases_id' => $releaseId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_values(array_unique($releaseIds))
        );

        return self::query()->insertOrIgnore($rows);
    }

    public static function getCart(mixed $uid): mixed
    {
        return self::query()
            ->with('release')
            ->whereHas('release')
            ->where(['users_id' => $uid])
            ->get();
    }

    /**
     * @return bool|mixed
     */
    public static function delCartByGuid(mixed $guids, mixed $userID)
    {
        if (! \is_array($guids)) {
            return false;
        }

        $guids = array_values(array_unique(array_filter(array_map(
            static fn ($guid): string => trim((string) $guid),
            $guids
        ))));

        if ($guids === []) {
            return false;
        }

        return self::query()
            ->where('users_id', $userID)
            ->whereIn('releases_id', Release::query()->select('id')->whereIn('guid', $guids))
            ->delete() > 0;
    }

    public static function delCartByUserAndRelease(mixed $guid, mixed $uid): void
    {
        self::query()
            ->where('users_id', $uid)
            ->whereIn('releases_id', Release::query()->select('id')->where('guid', $guid))
            ->delete();
    }

    /**
     * Remove multiple releases from a user's cart in one query.
     *
     * @param  list<int>  $releaseIds
     */
    public static function delCartByUserAndReleases(int $uid, array $releaseIds): void
    {
        if ($releaseIds === []) {
            return;
        }
        self::query()->where('users_id', $uid)->whereIn('releases_id', $releaseIds)->delete();
    }

    public static function delCartForRelease(mixed $rid): void
    {
        self::query()->where('releases_id', $rid)->delete();
    }
}
