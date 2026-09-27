<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseUnique.
 *
 * @property int $releases_id FK to releases.id.
 * @property mixed $uniqueid Unique_ID from mediainfo.
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique whereUniqueid($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseUnique query()
 */
class ReleaseUnique extends Model
{
    use HasCompositePrimaryKey;

    /**
     * @var string
     */
    protected $table = 'release_unique';

    /**
     * @var string
     */
    protected $primaryKey = 'releases_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    protected $dateFormat = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'releases_id',
        'uniqueid',
    ];

    /**
     * @return non-empty-list<string>
     */
    public function compositeKeyColumns(): array
    {
        return ['releases_id', 'uniqueid'];
    }
}
