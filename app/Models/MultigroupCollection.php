<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MultigroupCollection
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
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereAdded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereCollectionRegexesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereCollectionhash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereDateadded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereFilecheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereFilesize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereFromname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereNoise($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereReleasesId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereSubject($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereTotalfiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupCollection whereXref($value)
 * @mixin \Eloquent
 */
class MultigroupCollection extends Model
{
    //
}
