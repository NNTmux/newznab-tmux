<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCompositePrimaryKey;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ParHash.
 *
 * @property int $releases_id FK to releases.id
 * @property string $hash hash_16k block of par2
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash whereHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash whereReleasesId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ParHash query()
 */
class ParHash extends Model
{
    use HasCompositePrimaryKey;

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
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @return non-empty-list<string>
     */
    public function compositeKeyColumns(): array
    {
        return ['releases_id', 'hash'];
    }
}
