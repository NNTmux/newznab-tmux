<?php

namespace App\Models;

use Yadakhov\InsertOnDuplicateKey;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleaseRegex
 *
 * @property int $releases_id
 * @property int $collection_regex_id
 * @property int $naming_regex_id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex whereCollectionRegexId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex whereNamingRegexId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleaseRegex whereReleasesId($value)
 * @mixin \Eloquent
 */
class ReleaseRegex extends Model
{
    use InsertOnDuplicateKey;

    /**
     * @var bool
     */
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
     * @var array
     */
    protected $guarded = [];
}
