<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $releases_id
 * @property string $password
 * @property-read Release $release
 */
class ReleaseNzbPassword extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'releases_id';

    public $incrementing = false;

    protected $keyType = 'int';

    /** @var array<string> */
    protected $guarded = [];

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }
}
