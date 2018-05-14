<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MultigroupMissedPart
 *
 * @property int $id
 * @property int $numberid
 * @property int $groups_id FK to groups.id
 * @property bool $attempts
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupMissedPart whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupMissedPart whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupMissedPart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MultigroupMissedPart whereNumberid($value)
 * @mixin \Eloquent
 */
class MultigroupMissedPart extends Model
{
    //
}
