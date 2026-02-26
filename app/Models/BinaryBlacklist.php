<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\BinaryBlacklist.
 *
 * @property int $id
 * @property string|null $groupname
 * @property string $regex
 * @property int $msgcol
 * @property int $optype
 * @property int $status
 * @property string|null $description
 * @property string|null $last_activity
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereGroupname($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereLastActivity($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereMsgcol($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereOptype($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereRegex($value)
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist whereStatus($value)
 *
 * @mixin \Eloquent
 *
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|\App\Models\BinaryBlacklist query()
 */
class BinaryBlacklist extends Model
{
    /**
     * @var string
     */
    protected $table = 'binaryblacklist';

    /**
     * @var bool
     */
    public $timestamps = false;

    protected $dateFormat = false;

    /**
     * @var array<string>
     */
    protected $guarded = [];
}
