<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\DnzbFailure.
 *
 * @property int $release_id
 * @property int $users_id
 * @property int $failed
 * @property-read Release $release
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\DnzbFailure whereFailed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\DnzbFailure whereReleaseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\DnzbFailure whereUsersId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\DnzbFailure newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\DnzbFailure newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\DnzbFailure query()
 */
class DnzbFailure extends Model
{
    /**
     * @var string
     */
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'release_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    /**
     * Read failed downloads count for requested release_id.
     *
     *
     * @return bool|mixed
     */
    public static function getFailedCount(mixed $relId)
    {
        $result = self::query()->where('release_id', $relId)->value('failed');
        if (! empty($result)) {
            return $result;
        }

        return false;
    }

    public static function getCount(): int
    {
        return self::query()->count('release_id');
    }
}
