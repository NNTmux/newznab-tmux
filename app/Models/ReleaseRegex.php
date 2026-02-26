<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseRegex.
 *
 * @property int $releases_id
 * @property int $collection_regex_id
 * @property int $naming_regex_id
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex whereCollectionRegexId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex whereNamingRegexId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex whereReleasesId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex query()
 */
class ReleaseRegex extends Model
{
    protected $dateFormat = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];
}
