<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\UsersRelease.
 *
 * @property int $id
 * @property int $users_id
 * @property int $releases_id FK to releases.id
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Release, $this>
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
     * @return int|\Illuminate\Database\Eloquent\Builder
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

        $del = [];
        foreach ($guids as $guid) {
            $rel = Release::query()->where('guid', $guid)->first(['id']);
            if ($rel !== null) {
                $del[] = $rel['id'];
            }
        }

        return self::query()->whereIn('releases_id', $del)->where('users_id', $userID)->delete() === 1;
    }

    public static function delCartByUserAndRelease(mixed $guid, mixed $uid): void
    {
        $rel = Release::query()->where('guid', $guid)->first(['id']);
        if ($rel) {
            self::query()->where(['users_id' => $uid, 'releases_id' => $rel['id']])->delete();
        }
    }

    public static function delCartForRelease(mixed $rid): void
    {
        self::query()->where('releases_id', $rid)->delete();
    }
}
