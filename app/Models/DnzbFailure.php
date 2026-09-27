<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
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
    use HasCompositePrimaryKey;

    /**
     * @var string
     */
    protected $dateFormat = false;

    /**
     * @var string
     */
    protected $primaryKey = 'release_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return non-empty-list<string>
     */
    public function compositeKeyColumns(): array
    {
        return ['release_id', 'users_id'];
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'release_id');
    }

    /**
     * @return BelongsTo<User, $this>
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
