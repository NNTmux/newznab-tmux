<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\ReleasesGroup.
 *
 * @property int $releases_id FK to releases.id
 * @property int $groups_id FK to groups.id
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroup whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroup whereReleasesId($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroup query()
 */
class ReleasesGroup extends Model
{
    //
}
