<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Binary.
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
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary whereBinaryhash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary whereCollectionsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary whereCurrentparts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary whereFilenumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary wherePartcheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary wherePartsize($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary whereTotalparts($value)
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\Binary query()
 */
class Binary extends Model
{
    /**
     * @var bool
     */
    public $timestamps = false;
}
