<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\SharingSite.
 *
 * @property int $id
 * @property string $site_name
 * @property string $site_guid
 * @property string|null $last_time
 * @property string|null $first_time
 * @property bool $enabled
 * @property int $comments
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite whereComments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite whereEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite whereFirstTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite whereLastTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite whereSiteGuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite whereSiteName($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\SharingSite query()
 */
class SharingSite extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;
}
