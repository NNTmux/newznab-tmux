<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\MissedPart.
 *
 * @property int $id
 * @property int $numberid
 * @property int $groups_id FK to groups.id
 * @property bool $attempts
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MissedPart whereAttempts($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MissedPart whereGroupsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MissedPart whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MissedPart whereNumberid($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MissedPart newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MissedPart newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\MissedPart query()
 */
class MissedPart extends Model
{
    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];
}
