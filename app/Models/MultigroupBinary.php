<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MultigroupBinary
 *
 * @property int $id
 * @property string $name
 * @property int $collections_id
 * @property int $filenumber
 * @property int $totalparts
 * @property int $currentparts
 * @property bool $partcheck
 * @property int $partsize
 * @property mixed $binaryhash
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary whereBinaryhash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary whereCollectionsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary whereCurrentparts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary whereFilenumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary wherePartcheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary wherePartsize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupBinary whereTotalparts($value)
 * @mixin \Eloquent
 */
class MultigroupBinary extends Model
{
    //
}
