<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleasesGroup
 *
 * @property int $releases_id FK to releases.id
 * @property int $groups_id FK to groups.id
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroup whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroup whereReleasesId($value)
 * @mixin \Eloquent
 */
class ReleasesGroup extends Model
{
    //
}
