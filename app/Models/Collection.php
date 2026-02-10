<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Collection.
 *
 * @property int $id
 * @property string $subject
 * @property string $fromname
 * @property string|null $date
 * @property string $xref
 * @property int $totalfiles
 * @property int $groups_id
 * @property string $collectionhash
 * @property int $collection_regexes_id FK to collection_regexes.id
 * @property string|null $dateadded
 * @property string $added
 * @property bool $filecheck
 * @property int $filesize
 * @property int|null $releases_id
 * @property string $noise
 * @property string|null $udate Computed Unix timestamp from raw query
 * @property string|null $gname Computed group name from join
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereAdded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereCollectionRegexesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereCollectionhash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereDateadded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereFilecheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereFilesize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereFromname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereNoise($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereTotalfiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection whereXref($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Collection query()
 */
class Collection extends Model
{
    public $timestamps = false;
}
