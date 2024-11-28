<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program (see LICENSE.txt in the base directory.  If
 * not, see:.
 *
 * @link      <http://www.gnu.org/licenses/>.
 *
 * @author    niel
 * @copyright 2016 nZEDb
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\ReleasesGroups.
 *
 * @property int $releases_id FK to releases.id
 * @property int $groups_id FK to groups.id
 * @property-read Release $release
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroups whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroups whereReleasesId($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroups newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroups newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\ReleasesGroups query()
 */
class ReleasesGroups extends Model
{
    /**
     * @var string
     */

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $dateFormat = false;

    /**
     * @var array
     */
    protected $primaryKey = ['releases_id', 'groups_id'];

    /**
     * @var array
     */
    protected $guarded = [];

    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'releases_id');
    }
}
