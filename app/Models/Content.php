<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Content.
 *
 * @property int $id
 * @property string $title
 * @property string|null $url
 * @property string|null $body
 * @property string $metadescription
 * @property string $metakeywords
 * @property int $contenttype
 * @property int $showinmenu
 * @property int $status
 * @property int|null $ordinal
 * @property int $role
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereBody($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereContenttype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereMetadescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereMetakeywords($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereOrdinal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereShowinmenu($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content whereUrl($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Content query()
 */
class Content extends Model
{
    /**
     * @var string
     */
    protected $table = 'content';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    protected $dateFormat = false;

    /**
     * @var array
     */
    protected $guarded = [];
}
